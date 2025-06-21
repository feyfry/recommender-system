import os
import logging
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
import asyncio
import aiohttp
from fastapi import APIRouter, HTTPException, Query, Path
from pydantic import BaseModel, Field
import time
import re

# Setup router
router = APIRouter(
    prefix="/blockchain",
    tags=["blockchain data"],
    responses={404: {"description": "Not found"}},
)

# Setup logging with UTF-8 encoding fix
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# ⚡ ENHANCED: Smart filtering untuk token spam dan dust dengan deteksi yang lebih ketat
SPAM_PATTERNS = [
    r'claim.*reward',
    r'visit.*site',
    r'airdrop',
    r'free.*token',
    r'\.com',
    r'\.net',
    r'\.org',
    r'\.io',
    r'www\.',
    r'http',
    r'reward.*claim',
    r'СLАlМ',  # Cyrillic scam
    r'▷',      # Scam arrows
    r'🎁',      # Gift emoji
    r'\$\w+.*claim',
    r'[!@#$%^&*()+={}[\]|\\:";\'<>?,./]',  # Special characters in names
    r'^\d+USD',  # Starts with number+USD
    r'refID',    # Referral ID
    r'to claim', # Common scam phrase
    r'access.*to',  # Access to something
    r'bonus.*token', # Bonus tokens
    r'reward.*token', # Reward tokens
]

MIN_BALANCE_USD_THRESHOLD = 0.01  # $0.01 minimum untuk dihitung
MAX_TOKENS_TO_PRICE = 20  # ⚡ REDUCED: Maksimal 20 tokens yang dicek harganya (dari 50)
BATCH_SIZE = 3  # ⚡ REDUCED: Batch size untuk API calls (dari 5)

# Cache untuk menyimpan data onchain dan prices
_onchain_cache = {}
_price_cache = {}
_token_info_cache = {}
_cache_ttl = 300  # 5 menit

# Pydantic Models (same as before)
class TokenBalance(BaseModel):
    token_address: str
    token_name: str
    token_symbol: str
    balance: float
    balance_raw: str
    decimals: int
    usd_value: Optional[float] = None
    chain: str
    is_spam: Optional[bool] = False

class OnchainTransaction(BaseModel):
    tx_hash: str
    block_number: int
    timestamp: datetime
    from_address: str
    to_address: str
    value: float
    value_raw: str
    gas_used: int
    gas_price: str
    token_symbol: Optional[str] = None
    token_address: Optional[str] = None
    transaction_type: str  # 'native', 'token', 'contract'
    chain: str
    status: str

class WalletPortfolio(BaseModel):
    wallet_address: str
    total_usd_value: float
    native_balances: List[TokenBalance]
    token_balances: List[TokenBalance]
    last_updated: datetime
    chains_scanned: List[str]
    filtered_tokens_count: Optional[int] = 0

class OnchainAnalytics(BaseModel):
    wallet_address: str
    total_transactions: int
    unique_tokens_traded: int
    total_volume_usd: float
    most_traded_tokens: List[Dict[str, Any]]
    transaction_frequency: Dict[str, int]
    profit_loss_estimate: Optional[float] = None
    chains_activity: Dict[str, int]

# ⚡ ENHANCED: Smart spam detection dengan deteksi domain dan karakter aneh
def is_spam_token(token_name: str, token_symbol: str, balance: float = 0) -> bool:
    """Detect spam/scam tokens dengan smart filtering yang lebih ketat"""
    try:
        # Check for spam patterns
        combined_text = f"{token_name} {token_symbol}".lower()
        
        # ⚡ ENHANCED: More strict pattern matching
        for pattern in SPAM_PATTERNS:
            if re.search(pattern, combined_text, re.IGNORECASE):
                return True
        
        # ⚡ NEW: Check for suspicious token names
        if len(token_name) > 100:  # Token name terlalu panjang
            return True
            
        if len(token_symbol) > 20:  # Symbol terlalu panjang
            return True
            
        # ⚡ NEW: Check for domain-like patterns
        domain_patterns = ['.com', '.net', '.org', '.io', 'www.', 'http']
        if any(domain in combined_text for domain in domain_patterns):
            return True
            
        # ⚡ NEW: Check for weird characters in symbol
        if re.search(r'[^a-zA-Z0-9]', token_symbol):
            return True
            
        # ⚡ NEW: Check for numbers at start of name (common in scams)
        if re.match(r'^\d+\s*(usd|btc|eth|bnb)', token_name.lower()):
            return True
            
        # ⚡ NEW: Check for common scam words
        scam_words = ['bonus', 'claim', 'reward', 'airdrop', 'free', 'gift']
        if any(word in combined_text for word in scam_words):
            return True
        
        # Very large balances (likely fake tokens)
        if balance > 10000000:  # 10M+ tokens
            return True
        
        return False
        
    except Exception:
        return False

# ⚡ OPTIMIZED: Prioritize tokens yang SUDAH ADA USD VALUE
def prioritize_tokens_with_value(tokens: List[Dict]) -> List[Dict]:
    """⚡ NEW: Prioritize tokens yang sudah ada usd_value dari Moralis"""
    
    # Known valuable tokens (akan diprioritaskan)
    valuable_symbols = {
        'ETH', 'BTC', 'USDT', 'USDC', 'BNB', 'MATIC', 'AVAX',
        'WETH', 'WBTC', 'DAI', 'LINK', 'UNI', 'AAVE', 'CRV',
        'COMP', 'MKR', 'SNX', 'YFI', 'SUSHI', 'BAL', 'ENS'
    }
    
    # ⚡ STEP 1: Filter tokens yang sudah ada USD value atau valuable
    tokens_with_value = []
    valuable_tokens = []
    
    for token in tokens:
        symbol = token.get('symbol', '').upper()
        balance = token.get('balance', 0)
        
        # Skip spam tokens
        if is_spam_token(token.get('name', ''), symbol, balance):
            continue
            
        # Skip very small balances
        if balance < 0.000001:
            continue
        
        # ⚡ PRIORITY 1: Tokens yang sudah ada usd_value dari Moralis
        if token.get('usd_value') is not None and token.get('usd_value') > 0:
            tokens_with_value.append(token)
        # ⚡ PRIORITY 2: Known valuable tokens (tapi limit)
        elif symbol in valuable_symbols:
            valuable_tokens.append(token)
    
    # Sort by USD value (descending) dan balance
    tokens_with_value.sort(key=lambda x: x.get('usd_value', 0), reverse=True)
    valuable_tokens.sort(key=lambda x: x.get('balance', 0), reverse=True)
    
    # ⚡ RETURN: Tokens with value + limited valuable tokens
    result = tokens_with_value + valuable_tokens[:5]  # Max 5 valuable tanpa USD value
    
    logger.info(f"SUCCESS: Filtered to {len(result)} tokens: {len(tokens_with_value)} with USD value + {len(valuable_tokens[:5])} valuable tokens")
    
    return result[:MAX_TOKENS_TO_PRICE]

# ⚡ OPTIMIZED: Skip price fetching untuk tokens yang sudah ada USD value
async def fetch_token_prices_smart(session: aiohttp.ClientSession, tokens: List[Dict]) -> Dict[str, float]:
    """
    ⚡ SMART: Hanya fetch price untuk tokens yang belum ada USD value
    """
    try:
        # ⚡ STEP 1: Prioritize dan filter tokens
        prioritized_tokens = prioritize_tokens_with_value(tokens)
        
        logger.info(f"SUCCESS: Processing {len(prioritized_tokens)} prioritized tokens (skipping tokens with existing USD value)")
        
        # ⚡ STEP 2: Separate tokens yang perlu fetch vs yang sudah ada USD value
        tokens_need_price = []
        existing_prices = {}
        
        for token in prioritized_tokens:
            symbol = token.get('symbol', '')
            if symbol:
                # ⚡ Skip if already has USD value
                if token.get('usd_value') is not None and token.get('usd_value') > 0:
                    # Calculate price from existing USD value
                    balance = token.get('balance', 0)
                    if balance > 0:
                        existing_prices[symbol] = token['usd_value'] / balance
                else:
                    # Need to fetch price
                    tokens_need_price.append(token)
        
        logger.info(f"CHECKED: {len(existing_prices)} tokens already have USD value, {len(tokens_need_price)} need price fetch")
        
        # ⚡ STEP 3: Fetch prices only for tokens that need it (VERY LIMITED)
        if tokens_need_price:
            # Limit to only top 5 tokens that need pricing
            tokens_need_price = tokens_need_price[:5]
            
            logger.info(f"FINDING: Fetching prices for only {len(tokens_need_price)} tokens")
            
            for token in tokens_need_price:
                symbol = token.get('symbol', '')
                address = token.get('address', '')
                chain = token.get('chain', '')
                
                if symbol and symbol not in existing_prices:
                    try:
                        price = await get_token_price_auto(session, symbol, address, chain)
                        if price > 0:
                            existing_prices[symbol] = price
                        # ⚡ Add delay to respect rate limits
                        await asyncio.sleep(0.8)  # 800ms delay between calls
                    except Exception as e:
                        logger.warning(f"Error fetching price for {symbol}: {str(e)}")
        
        logger.info(f"SUCCESS: Final prices collected: {len(existing_prices)} tokens")
        return existing_prices
        
    except Exception as e:
        logger.error(f"Error in smart token price fetching: {str(e)}")
        return {}

# ⚡ ENHANCED: Fetch Moralis portfolio dengan smart filtering dan skip price fetching
async def fetch_moralis_portfolio(session: aiohttp.ClientSession, wallet_address: str) -> Dict:
    """⚡ OPTIMIZED: Fetch portfolio dengan skip unnecessary price fetching"""
    try:
        config = BLOCKCHAIN_APIS['moralis']
        headers = {
            'X-API-Key': config['api_key'],
            'Content-Type': 'application/json'
        }
        
        portfolio_data = {
            'native_balances': [],
            'token_balances': [],
            'total_usd_value': 0.0,
            'filtered_tokens_count': 0
        }
        
        # Collect all tokens for smart processing
        all_tokens = []
        total_tokens_found = 0
        
        # Fetch data dari multiple chains
        for chain in config['chains']:
            try:
                # Native balance
                native_url = f"{config['api_url']}/{wallet_address}/balance?chain={chain}"
                async with session.get(native_url, headers=headers) as response:
                    if response.status == 200:
                        native_data = await response.json()
                        if native_data.get('balance'):
                            balance_wei = int(native_data['balance'])
                            balance_eth = balance_wei / 1e18
                            
                            # Map chain ke symbol
                            chain_symbol_map = {
                                'eth': 'ETH',
                                'bsc': 'BNB', 
                                'polygon': 'MATIC',
                                'avalanche': 'AVAX'
                            }
                            symbol = chain_symbol_map.get(chain, chain.upper())
                            
                            # Skip if balance too small
                            if balance_eth > 0.000001:
                                all_tokens.append({
                                    'symbol': symbol,
                                    'address': '0x0',
                                    'chain': chain,
                                    'balance': balance_eth,
                                    'usd_value': None  # Will be fetched if needed
                                })
                                
                                portfolio_data['native_balances'].append({
                                    'token_address': '0x0',
                                    'token_name': symbol,
                                    'token_symbol': symbol,
                                    'balance': balance_eth,
                                    'balance_raw': str(balance_wei),
                                    'decimals': 18,
                                    'chain': chain,
                                    'usd_value': None,  # Will be calculated
                                    'is_spam': False
                                })
                
                # Token balances
                tokens_url = f"{config['api_url']}/{wallet_address}/erc20?chain={chain}"
                async with session.get(tokens_url, headers=headers) as response:
                    if response.status == 200:
                        tokens_data = await response.json()
                        total_tokens_found += len(tokens_data)
                        
                        for token in tokens_data:
                            balance_raw = int(token.get('balance', 0))
                            decimals = int(token.get('decimals', 18))
                            balance = balance_raw / (10 ** decimals)
                            
                            symbol = token.get('symbol', 'UNKNOWN')
                            name = token.get('name', symbol)
                            address = token.get('token_address', '')
                            
                            # ⚡ Enhanced spam detection
                            is_spam = is_spam_token(name, symbol, balance)
                            
                            if balance > 0:  # Only include non-zero balances
                                if not is_spam:
                                    # ⚡ Check if token already has USD value from Moralis
                                    existing_usd_value = token.get('usd_price')  # Moralis might provide this
                                    calculated_usd_value = None
                                    
                                    if existing_usd_value and existing_usd_value > 0:
                                        calculated_usd_value = balance * existing_usd_value
                                    
                                    all_tokens.append({
                                        'symbol': symbol,
                                        'address': address,
                                        'chain': chain,
                                        'balance': balance,
                                        'usd_value': calculated_usd_value
                                    })
                                else:
                                    portfolio_data['filtered_tokens_count'] += 1
                                
                                portfolio_data['token_balances'].append({
                                    'token_address': address,
                                    'token_name': name,
                                    'token_symbol': symbol,
                                    'balance': balance,
                                    'balance_raw': str(balance_raw),
                                    'decimals': decimals,
                                    'chain': chain,
                                    'usd_value': calculated_usd_value,
                                    'is_spam': is_spam
                                })
                
                # Small delay antara requests
                await asyncio.sleep(0.1)
                
            except Exception as e:
                logger.warning(f"Error fetching {chain} data: {str(e)}")
                continue
        
        logger.info(f"CHECKED: Found {total_tokens_found} total tokens, filtered {portfolio_data['filtered_tokens_count']} spam tokens")
        
        # ⚡ SMART: Only fetch prices for high-priority tokens without USD value
        if all_tokens:
            token_prices = await fetch_token_prices_smart(session, all_tokens)
            
            # Calculate USD values
            total_usd_value = 0.0
            
            # Update native balances dengan USD values
            for balance in portfolio_data['native_balances']:
                symbol = balance['token_symbol']
                if symbol in token_prices:
                    usd_value = balance['balance'] * token_prices[symbol]
                    
                    # ⚡ Validate USD value (prevent crazy numbers)
                    if usd_value > 0 and usd_value < 1e10:  # Max $10B sanity check (reduced from 1T)
                        balance['usd_value'] = usd_value
                        total_usd_value += usd_value
            
            # Update token balances dengan USD values  
            valid_tokens_count = 0
            for token in portfolio_data['token_balances']:
                if not token.get('is_spam', False):  # Skip spam tokens
                    # Use existing USD value if available
                    if token.get('usd_value') is not None and token['usd_value'] > 0:
                        total_usd_value += token['usd_value']
                        valid_tokens_count += 1
                    else:
                        # Try to get price from fetched prices
                        symbol = token['token_symbol']
                        if symbol in token_prices:
                            usd_value = token['balance'] * token_prices[symbol]
                            
                            # ⚡ Validate USD value
                            if usd_value > 0 and usd_value < 1e10:  # Max $10B sanity check
                                token['usd_value'] = usd_value
                                total_usd_value += usd_value
                                valid_tokens_count += 1
            
            portfolio_data['total_usd_value'] = total_usd_value
            
            logger.info(f"SUCCESS: Smart-calculated portfolio: {len(portfolio_data['native_balances'])} native + {valid_tokens_count} valued tokens")
            logger.info(f"PRICE: Total USD: ${total_usd_value:.8f} (realistic calculation)")
            logger.info(f"BANNED: Filtered {portfolio_data['filtered_tokens_count']} spam tokens")
        
        return portfolio_data
        
    except Exception as e:
        logger.error(f"Error fetching Moralis portfolio: {str(e)}")
        return {'native_balances': [], 'token_balances': [], 'total_usd_value': 0.0, 'filtered_tokens_count': 0}

# ⚡ Keep existing helper functions but optimize them
async def get_token_price_auto(session: aiohttp.ClientSession, token_symbol: str, token_address: str = None, chain: str = None) -> float:
    """
    ⚡ OPTIMIZED: Sistem otomatis untuk mendapatkan harga token dengan timeout yang lebih ketat
    """
    coingecko_api_key = os.environ.get('COINGECKO_API_KEY', '')
    base_url = "https://api.coingecko.com/api/v3"
    
    # Build headers
    headers = {}
    if coingecko_api_key and coingecko_api_key not in ['CG-CC***', 'YOUR-API-KEY-HERE']:
        headers['x-cg-demo-api-key'] = coingecko_api_key
    
    try:
        # ⚡ PRIORITY 1: Try native token mapping first (fastest)
        native_symbols = {
            'ETH': 'ethereum',
            'BNB': 'binancecoin', 
            'MATIC': 'matic-network',
            'AVAX': 'avalanche-2',
            'AVALANCHE': 'avalanche-2'
        }
        
        if token_symbol.upper() in native_symbols:
            coin_id = native_symbols[token_symbol.upper()]
            price_url = f"{base_url}/simple/price"
            params = {
                'ids': coin_id,
                'vs_currencies': 'usd'
            }
            
            async with session.get(price_url, params=params, headers=headers, timeout=aiohttp.ClientTimeout(total=3)) as response:
                if response.status == 200:
                    data = await response.json()
                    if coin_id in data and 'usd' in data[coin_id]:
                        price = float(data[coin_id]['usd'])
                        logger.info(f"SUCCESS: Native price for {token_symbol}: ${price:.8f}")
                        return price
        
        # ⚡ PRIORITY 2: Try contract address lookup (if available)
        if token_address and token_address != '0x0' and chain:
            platform_map = {
                'eth': 'ethereum',
                'ethereum': 'ethereum',
                'bsc': 'binance-smart-chain',
                'binance_smart_chain': 'binance-smart-chain',
                'polygon': 'polygon-pos',
                'avalanche': 'avalanche'
            }
            
            platform = platform_map.get(chain.lower())
            if platform:
                contract_url = f"{base_url}/simple/token_price/{platform}"
                params = {
                    'contract_addresses': token_address.lower(),
                    'vs_currencies': 'usd'
                }
                
                async with session.get(contract_url, params=params, headers=headers, timeout=aiohttp.ClientTimeout(total=3)) as response:
                    if response.status == 200:
                        data = await response.json()
                        if token_address.lower() in data and 'usd' in data[token_address.lower()]:
                            price = float(data[token_address.lower()]['usd'])
                            logger.info(f"SUCCESS: Contract price for {token_symbol}: ${price:.8f}")
                            return price
        
        # ⚡ Skip symbol search for non-major tokens to save time
        logger.info(f"SKIPPED: Skipped price fetch for {token_symbol} (not major token)")
        return 0.0
        
    except Exception as e:
        logger.warning(f"WARNING: Price fetch error for {token_symbol}: {str(e)}")
        return 0.0

# Rest of the code remains the same but with API Configuration
BLOCKCHAIN_APIS = {
    'ethereum': {
        'api_url': 'https://api.etherscan.io/api',
        'api_key': os.environ.get('ETHERSCAN_API_KEY', 'YourApiKeyToken'),
        'native_symbol': 'ETH'
    },
    'binance_smart_chain': {
        'api_url': 'https://api.bscscan.com/api', 
        'api_key': os.environ.get('BSCSCAN_API_KEY', 'YourApiKeyToken'),
        'native_symbol': 'BNB'
    },
    'polygon': {
        'api_url': 'https://api.polygonscan.com/api',
        'api_key': os.environ.get('POLYGONSCAN_API_KEY', 'YourApiKeyToken'), 
        'native_symbol': 'MATIC'
    },
    'moralis': {
        'api_url': 'https://deep-index.moralis.io/api/v2.2',
        'api_key': os.environ.get('MORALIS_API_KEY', 'YourApiKeyToken'),
        'chains': ['eth', 'bsc', 'polygon', 'avalanche']
    }
}

# Keep all the existing helper functions and API endpoints
async def fetch_ethereum_data(session: aiohttp.ClientSession, wallet_address: str, api_type: str) -> Dict:
    """Fetch data dari Etherscan-like APIs"""
    try:
        config = BLOCKCHAIN_APIS['ethereum']
        
        if api_type == 'balance':
            url = f"{config['api_url']}?module=account&action=balance&address={wallet_address}&tag=latest&apikey={config['api_key']}"
        elif api_type == 'token_balance':
            url = f"{config['api_url']}?module=account&action=tokentx&address={wallet_address}&startblock=0&endblock=99999999&sort=desc&apikey={config['api_key']}"
        elif api_type == 'transactions':
            url = f"{config['api_url']}?module=account&action=txlist&address={wallet_address}&startblock=0&endblock=99999999&sort=desc&apikey={config['api_key']}"
        
        async with session.get(url) as response:
            if response.status == 200:
                data = await response.json()
                if data.get('status') == '1':
                    return data.get('result', [])
            return []
    except Exception as e:
        logger.error(f"Error fetching Ethereum data: {str(e)}")
        return []

async def fetch_onchain_transactions(session: aiohttp.ClientSession, wallet_address: str, limit: int = 50) -> List[Dict]:
    """Fetch transaksi onchain dari multiple chains"""
    try:
        all_transactions = []
        
        # Fetch dari Ethereum via Etherscan
        eth_txs = await fetch_ethereum_data(session, wallet_address, 'transactions')
        for tx in eth_txs[:limit]:
            try:
                all_transactions.append({
                    'tx_hash': tx.get('hash'),
                    'block_number': int(tx.get('blockNumber', 0)),
                    'timestamp': datetime.fromtimestamp(int(tx.get('timeStamp', 0))),
                    'from_address': tx.get('from'),
                    'to_address': tx.get('to'),
                    'value': float(tx.get('value', 0)) / 1e18,
                    'value_raw': tx.get('value', '0'),
                    'gas_used': int(tx.get('gasUsed', 0)),
                    'gas_price': tx.get('gasPrice', '0'),
                    'transaction_type': 'native',
                    'chain': 'ethereum',
                    'status': 'success' if tx.get('txreceipt_status') == '1' else 'failed'
                })
            except Exception as e:
                logger.warning(f"Error parsing transaction: {str(e)}")
                continue
        
        # Sort by timestamp descending
        all_transactions.sort(key=lambda x: x['timestamp'], reverse=True)
        return all_transactions[:limit]
        
    except Exception as e:
        logger.error(f"Error fetching onchain transactions: {str(e)}")
        return []

def calculate_portfolio_analytics(transactions: List[Dict], portfolio: Dict) -> Dict:
    """Hitung analytics dengan AUTO USD volume calculation"""
    try:
        analytics = {
            'total_transactions': len(transactions),
            'unique_tokens_traded': 0,
            'total_volume_usd': 0.0,
            'most_traded_tokens': [],
            'transaction_frequency': {},
            'chains_activity': {}
        }
        
        # Analisis token yang diperdagangkan
        token_activity = {}
        chain_activity = {}
        
        # Calculate total USD volume from portfolio
        total_usd_from_portfolio = portfolio.get('total_usd_value', 0.0)
        
        for tx in transactions:
            chain = tx.get('chain', 'unknown')
            chain_activity[chain] = chain_activity.get(chain, 0) + 1
            
            # Frequency by date
            tx_date = tx['timestamp'].strftime('%Y-%m-%d')
            analytics['transaction_frequency'][tx_date] = analytics['transaction_frequency'].get(tx_date, 0) + 1
            
            # Token activity dengan simplified USD volume estimation
            token_symbol = tx.get('token_symbol', 'ETH')
            if token_symbol not in token_activity:
                token_activity[token_symbol] = {'count': 0, 'volume': 0.0, 'volume_usd': 0.0}
            
            token_activity[token_symbol]['count'] += 1
            token_value = tx.get('value', 0)
            token_activity[token_symbol]['volume'] += token_value
            
            # Simplified USD volume estimation
            if token_symbol == 'ETH':
                # Use estimated ETH price for volume calculation
                estimated_eth_price = 3400.0  # Fallback
                usd_volume = token_value * estimated_eth_price
                token_activity[token_symbol]['volume_usd'] += usd_volume
                analytics['total_volume_usd'] += usd_volume
        
        analytics['unique_tokens_traded'] = len(token_activity)
        analytics['chains_activity'] = chain_activity
        
        # Most traded tokens dengan USD volume
        sorted_tokens = sorted(token_activity.items(), key=lambda x: x[1]['count'], reverse=True)
        analytics['most_traded_tokens'] = [
            {
                'symbol': symbol, 
                'trade_count': data['count'], 
                'volume': data['volume'],
                'volume_usd': data.get('volume_usd', 0.0)
            }
            for symbol, data in sorted_tokens[:10]
        ]
        
        return analytics
        
    except Exception as e:
        logger.error(f"Error calculating analytics: {str(e)}")
        return {}

# Keep all API endpoints the same as original but with improved error handling
@router.get("/portfolio/{wallet_address}")
async def get_wallet_portfolio(
    wallet_address: str = Path(..., description="Wallet address"),
    chains: Optional[List[str]] = Query(None, description="Chains to scan (eth, bsc, polygon)")
) -> WalletPortfolio:
    """⚡ OPTIMIZED: Portfolio dengan SMART USD calculations dan spam filtering yang ketat"""
    
    cache_key = f"portfolio_{wallet_address}_{','.join(chains or ['all'])}"
    
    # Check cache
    if cache_key in _onchain_cache:
        cache_entry = _onchain_cache[cache_key]
        if datetime.now() < cache_entry['expires']:
            logger.info(f"OPTIMIZED: Returning cached portfolio for {wallet_address}")
            return cache_entry['data']
    
    try:
        # ⚡ VALIDASI: Pastikan wallet address format valid
        if not wallet_address or len(wallet_address) < 40:
            raise HTTPException(status_code=400, detail="Invalid wallet address format")
        
        async with aiohttp.ClientSession() as session:
            # ⚡ Fetch portfolio menggunakan optimized method
            portfolio_data = await fetch_moralis_portfolio(session, wallet_address)
            
            # Create response
            portfolio = WalletPortfolio(
                wallet_address=wallet_address,
                total_usd_value=portfolio_data.get('total_usd_value', 0.0),
                native_balances=[TokenBalance(**balance) for balance in portfolio_data.get('native_balances', [])],
                token_balances=[TokenBalance(**balance) for balance in portfolio_data.get('token_balances', [])],
                last_updated=datetime.now(),
                chains_scanned=chains or ['eth', 'bsc', 'polygon'],
                filtered_tokens_count=portfolio_data.get('filtered_tokens_count', 0)
            )
            
            # Cache hasil
            _onchain_cache[cache_key] = {
                'data': portfolio,
                'expires': datetime.now() + timedelta(seconds=_cache_ttl)
            }
            
            logger.info(f"OPTIMIZED: FAST portfolio for {wallet_address}: {len(portfolio.native_balances)} native + {len(portfolio.token_balances)} tokens, USD Total: ${portfolio.total_usd_value:.8f}")
            
            return portfolio
    
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"Error getting wallet portfolio: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

# Keep all other API endpoints the same...
@router.get("/transactions/{wallet_address}")
async def get_wallet_transactions(
    wallet_address: str = Path(..., description="Wallet address"),
    limit: int = Query(50, ge=1, le=200, description="Number of transactions to fetch"),
    chains: Optional[List[str]] = Query(None, description="Chains to scan")
) -> List[OnchainTransaction]:
    """Mendapatkan transaksi onchain dari wallet address"""
    
    cache_key = f"transactions_{wallet_address}_{limit}_{','.join(chains or ['all'])}"
    
    # Check cache
    if cache_key in _onchain_cache:
        cache_entry = _onchain_cache[cache_key]
        if datetime.now() < cache_entry['expires']:
            logger.info(f"Returning cached transactions for {wallet_address}")
            return cache_entry['data']
    
    try:
        # ⚡ VALIDASI: Pastikan wallet address format valid
        if not wallet_address or len(wallet_address) < 40:
            raise HTTPException(status_code=400, detail="Invalid wallet address format")
        
        async with aiohttp.ClientSession() as session:
            transactions_data = await fetch_onchain_transactions(session, wallet_address, limit)
            
            # Convert to response model
            transactions = [OnchainTransaction(**tx) for tx in transactions_data]
            
            # Cache hasil
            _onchain_cache[cache_key] = {
                'data': transactions,
                'expires': datetime.now() + timedelta(seconds=_cache_ttl)
            }
            
            logger.info(f"Successfully fetched {len(transactions)} transactions for {wallet_address}")
            
            return transactions
    
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"Error getting wallet transactions: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/analytics/{wallet_address}")
async def get_wallet_analytics(
    wallet_address: str = Path(..., description="Wallet address"),
    days: int = Query(30, ge=1, le=365, description="Days to analyze")
) -> OnchainAnalytics:
    """⚡ OPTIMIZED: Analytics dengan AUTO USD volume calculation"""
    
    cache_key = f"analytics_{wallet_address}_{days}"
    
    # Check cache
    if cache_key in _onchain_cache:
        cache_entry = _onchain_cache[cache_key]
        if datetime.now() < cache_entry['expires']:
            return cache_entry['data']
    
    try:
        # ⚡ VALIDASI: Pastikan wallet address format valid
        if not wallet_address or len(wallet_address) < 40:
            raise HTTPException(status_code=400, detail="Invalid wallet address format")
        
        async with aiohttp.ClientSession() as session:
            # Fetch transactions dan portfolio
            transactions_data = await fetch_onchain_transactions(session, wallet_address, 500)
            portfolio_data = await fetch_moralis_portfolio(session, wallet_address)
            
            # Calculate analytics dengan AUTO USD volume
            analytics_data = calculate_portfolio_analytics(transactions_data, portfolio_data)
            analytics_data['wallet_address'] = wallet_address
            
            analytics = OnchainAnalytics(**analytics_data)
            
            # Cache hasil
            _onchain_cache[cache_key] = {
                'data': analytics,
                'expires': datetime.now() + timedelta(seconds=_cache_ttl * 2)  # Cache lebih lama
            }
            
            logger.info(f"SUCCESS: FAST analytics for {wallet_address}: {analytics.total_transactions} txs, {analytics.unique_tokens_traded} tokens, USD Volume: ${analytics.total_volume_usd:.8f}")
            
            return analytics
    
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"Error getting wallet analytics: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.post("/cache/clear")
async def clear_onchain_cache():
    """Clear onchain data cache"""
    global _onchain_cache, _price_cache, _token_info_cache
    
    try:
        cache_size = len(_onchain_cache)
        price_cache_size = len(_price_cache)
        token_cache_size = len(_token_info_cache)
        
        _onchain_cache = {}
        _price_cache = {}
        _token_info_cache = {}
        
        logger.info(f"All caches cleared: {cache_size} onchain + {price_cache_size} price + {token_cache_size} token entries")
        
        return {"message": f"All caches cleared ({cache_size} onchain, {price_cache_size} price, {token_cache_size} token entries)"}
    
    except Exception as e:
        logger.error(f"Error clearing cache: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/health")
async def blockchain_health_check():
    """Health check untuk blockchain API services"""
    try:
        status = {
            'moralis_api': 'unknown',
            'etherscan_api': 'unknown',
            'coingecko_api': 'unknown',
            'cache_entries': len(_onchain_cache),
            'price_cache_entries': len(_price_cache),
            'token_cache_entries': len(_token_info_cache),
            'timestamp': datetime.now(),
            'api_keys_configured': {
                'moralis': bool(os.environ.get('MORALIS_API_KEY')),
                'etherscan': bool(os.environ.get('ETHERSCAN_API_KEY')),
                'bscscan': bool(os.environ.get('BSCSCAN_API_KEY')),
                'polygonscan': bool(os.environ.get('POLYGONSCAN_API_KEY')),
                'coingecko': bool(os.environ.get('COINGECKO_API_KEY'))
            },
            'optimization_status': {
                'smart_filtering': 'enabled',
                'spam_detection': 'enhanced',
                'price_fetching': 'minimal',
                'max_tokens_to_price': MAX_TOKENS_TO_PRICE,
                'batch_size': BATCH_SIZE
            }
        }
        
        # Quick health check untuk APIs
        try:
            async with aiohttp.ClientSession() as session:
                # Test Moralis API
                moralis_key = os.environ.get('MORALIS_API_KEY')
                if moralis_key and moralis_key != 'YourApiKeyToken':
                    headers = {'X-API-Key': moralis_key}
                    test_url = f"{BLOCKCHAIN_APIS['moralis']['api_url']}/dateToBlock?chain=eth&date=2024-01-01"
                    async with session.get(test_url, headers=headers, timeout=aiohttp.ClientTimeout(total=5)) as response:
                        status['moralis_api'] = 'healthy' if response.status == 200 else f'error_status_{response.status}'
                else:
                    status['moralis_api'] = 'api_key_missing'
                
                # Test CoinGecko API dengan minimal test
                coingecko_key = os.environ.get('COINGECKO_API_KEY')
                if coingecko_key:
                    headers = {'x-cg-demo-api-key': coingecko_key}
                    test_url = f"https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd"
                    async with session.get(test_url, headers=headers, timeout=aiohttp.ClientTimeout(total=3)) as response:
                        status['coingecko_api'] = 'healthy' if response.status == 200 else f'error_status_{response.status}'
                else:
                    status['coingecko_api'] = 'api_key_missing'
                    
        except Exception as e:
            status['moralis_api'] = f'error: {str(e)}'
            status['coingecko_api'] = f'error: {str(e)}'
        
        logger.info(f"Optimized blockchain health check: Moralis={status['moralis_api']}, CoinGecko={status['coingecko_api']}, Cache={status['cache_entries']} entries")
        
        return status
    
    except Exception as e:
        logger.error(f"Health check error: {str(e)}")
        return {"status": "error", "message": str(e), "timestamp": datetime.now()}