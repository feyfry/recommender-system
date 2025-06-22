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
MAX_TOKENS_TO_SHOW = 50  # ⚡ INCREASED: Show more tokens for pagination (from 20)
BATCH_SIZE = 3  # ⚡ Keep batch size small untuk stability

# Cache untuk menyimpan data onchain dan prices
_onchain_cache = {}
_price_cache = {}
_token_info_cache = {}
_cache_ttl = 300  # 5 menit

# Pydantic Models
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

# ⚡ ENHANCED: OnchainAnalytics model dengan multi-chain support
class OnchainAnalytics(BaseModel):
    wallet_address: str
    total_transactions: int
    unique_tokens_traded: int
    total_volume_usd: float
    most_traded_tokens: List[Dict[str, Any]]
    transaction_frequency: Dict[str, int]
    chains_activity: Dict[str, int]
    profit_loss_estimate: Optional[float] = None
    
    # ⚡ NEW: Multi-chain specific fields
    selected_chain: Optional[str] = None
    chain_specific_data: Optional[Dict[str, Any]] = None
    cross_chain_volume: Optional[float] = 0.0
    chain_dominance: Optional[Dict[str, float]] = {}
    diversification_score: Optional[float] = 0.0
    
    # ⚡ NEW: Performance metrics
    response_time_ms: Optional[int] = None
    cache_hit: Optional[bool] = False
    chains_processed: Optional[List[str]] = []
    errors_encountered: Optional[List[str]] = []

# ⚡ NEW: Chain-specific analytics model
class ChainSpecificAnalytics(BaseModel):
    chain: str
    native_token: str
    total_transactions: int
    unique_tokens: int
    volume_usd: float
    latest_transaction_date: Optional[datetime] = None
    top_tokens: List[Dict[str, Any]] = []
    transaction_distribution: Dict[str, int] = {}
    average_gas_used: Optional[float] = None
    
    # ⚡ Performance metrics per chain
    fetch_time_ms: Optional[int] = None
    success_rate: Optional[float] = None
    data_quality_score: Optional[float] = None

# ⚡ ENHANCED: Multi-chain token balance dengan cross-chain info
class MultiChainTokenBalance(BaseModel):
    token_address: str
    token_name: str  
    token_symbol: str
    balance: float
    balance_raw: str
    decimals: int
    usd_value: Optional[float] = None
    chain: str
    is_spam: Optional[bool] = False
    
    # ⚡ NEW: Cross-chain data
    cross_chain_equivalent: Optional[List[Dict[str, Any]]] = []
    total_cross_chain_balance: Optional[float] = None
    is_native_token: Optional[bool] = False
    bridge_addresses: Optional[List[str]] = []

# ⚡ NEW: Multi-chain portfolio aggregation
class MultiChainPortfolio(BaseModel):
    wallet_address: str
    total_usd_value: float
    total_native_value: float
    total_alt_token_value: float
    
    # Chain-specific breakdowns
    chain_breakdowns: Dict[str, Dict[str, Any]]
    native_balances: List[MultiChainTokenBalance]
    token_balances: List[MultiChainTokenBalance]
    
    # Cross-chain analytics
    cross_chain_exposure: Dict[str, float]
    diversification_metrics: Dict[str, float]
    chain_correlation_scores: Dict[str, float]
    
    # Metadata
    last_updated: datetime
    chains_scanned: List[str]
    filtered_tokens_count: int = 0
    processing_stats: Dict[str, Any] = {}

# ⚡ NEW: Cross-chain transaction analysis
class CrossChainTransaction(BaseModel):
    primary_chain: str
    related_chains: List[str]
    transaction_group_id: str
    total_value_usd: float
    bridge_protocol: Optional[str] = None
    estimated_fees_usd: Optional[float] = None
    completion_time_minutes: Optional[int] = None
    success_rate: Optional[float] = None

# ⚡ NEW: Multi-chain analytics insights
class MultiChainInsights(BaseModel):
    wallet_address: str
    
    # Chain usage patterns
    preferred_chains: List[str]
    chain_usage_distribution: Dict[str, float]
    cross_chain_activity_score: float
    
    # Trading patterns
    multi_chain_arbitrage_opportunities: int
    cross_chain_correlation_trades: int
    chain_migration_patterns: Dict[str, Any]
    
    # Risk and diversification
    chain_concentration_risk: float
    diversification_recommendation: str
    optimal_chain_allocation: Dict[str, float]
    
    # Performance insights
    best_performing_chain: str
    worst_performing_chain: str
    cross_chain_efficiency_score: float
    
    # Recommendations
    recommended_actions: List[str]
    risk_warnings: List[str]
    optimization_suggestions: List[str]

# ⚡ ENHANCED: Error handling untuk multi-chain
class MultiChainError(BaseModel):
    error_type: str
    chain: str
    message: str
    timestamp: datetime
    retry_count: int = 0
    recoverable: bool = True
    
class MultiChainResponse(BaseModel):
    success: bool
    data: Optional[Dict[str, Any]] = None
    errors: List[MultiChainError] = []
    partial_success: bool = False
    chains_processed: List[str] = []
    chains_failed: List[str] = []
    processing_time_ms: int
    cache_stats: Dict[str, Any] = {}

# ⚡ ENHANCED: Smart spam detection dengan pattern yang lebih ketat
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
        if len(token_name) > 100:
            return True
            
        if len(token_symbol) > 20:
            return True
            
        # ⚡ NEW: Check for domain-like patterns
        domain_patterns = ['.com', '.net', '.org', '.io', 'www.', 'http']
        if any(domain in combined_text for domain in domain_patterns):
            return True
            
        # ⚡ NEW: Check for weird characters in symbol
        if re.search(r'[^a-zA-Z0-9]', token_symbol):
            return True
            
        # ⚡ NEW: Check for numbers at start of name
        if re.match(r'^\d+\s*(usd|btc|eth|bnb)', token_name.lower()):
            return True
            
        # ⚡ NEW: Check for common scam words
        scam_words = ['bonus', 'claim', 'reward', 'airdrop', 'free', 'gift']
        if any(word in combined_text for word in scam_words):
            return True
        
        # Very large balances (likely fake tokens)
        if balance > 10000000:
            return True
        
        return False
        
    except Exception:
        return False
    
async def get_onchain_analytics(session: aiohttp.ClientSession, wallet_address: str, selected_chain: str = None) -> Dict:
    """⚡ FIXED: Analytics dengan proper multi-chain data processing"""
    try:
        config = BLOCKCHAIN_APIS['moralis']
        headers = {
            'X-API-Key': config['api_key'],
            'Content-Type': 'application/json'
        }
        
        analytics_data = {
            'wallet_address': wallet_address,
            'total_transactions': 0,
            'unique_tokens_traded': 0,
            'total_volume_usd': 0.0,
            'most_traded_tokens': [],
            'transaction_frequency': {},
            'chains_activity': {},
            'selected_chain': selected_chain,
            'chain_specific_data': None,
            'cross_chain_volume': 0.0,
            'chain_dominance': {},
            'diversification_score': 0.0,
            'chains_processed': [],
            'errors_encountered': []
        }

        # ⚡ FIXED: Use existing transaction fetching logic instead of new implementation
        # Leverage the working fetch_onchain_transactions function
        all_transactions = []
        chain_transactions = {}
        
        # Determine which chains to process
        target_chains = [selected_chain] if selected_chain else config['chains']
        
        logger.info(f"ANALYTICS: Processing {len(target_chains)} chains: {target_chains}")
        
        # ⚡ FIXED: Use the working transaction fetching approach
        for chain in target_chains:
            try:
                # Get transactions for this chain using existing working method
                transactions_url = f"{config['api_url']}/{wallet_address}"
                
                # ⚡ Use the transaction endpoint that we know works
                if chain == 'eth' or chain == 'ethereum':
                    # Use existing transaction fetch that works for ETH
                    eth_txs = await fetch_ethereum_data(session, wallet_address, 'transactions')
                    chain_txs = []
                    
                    for tx in eth_txs[:200]:  # Limit to 200
                        try:
                            processed_tx = {
                                'hash': tx.get('hash', ''),
                                'block_number': int(tx.get('blockNumber', 0)),
                                'timestamp': datetime.fromtimestamp(int(tx.get('timeStamp', 0))).strftime('%Y-%m-%d'),
                                'from_address': tx.get('from', ''),
                                'to_address': tx.get('to', ''),
                                'value': float(tx.get('value', 0)) / 1e18,
                                'token_symbol': 'ETH',
                                'token_address': '',
                                'chain': 'ethereum',
                                'transaction_type': 'native'
                            }
                            chain_txs.append(processed_tx)
                            all_transactions.append(processed_tx)
                        except Exception as e:
                            logger.warning(f"Error processing ETH transaction: {e}")
                            continue
                    
                    chain_transactions['ethereum'] = len(chain_txs)
                    logger.info(f"SUCCESS: Processed {len(chain_txs)} ETH transactions")
                
                else:
                    # For other chains, try Moralis API
                    try:
                        chain_url = f"{config['api_url']}/{wallet_address}/erc20/transfers"
                        params = {
                            'chain': chain,
                            'limit': 100,
                            'order': 'DESC'
                        }
                        
                        async with session.get(chain_url, headers=headers, params=params, 
                                             timeout=aiohttp.ClientTimeout(total=10)) as response:
                            if response.status == 200:
                                data = await response.json()
                                raw_txs = data.get('result', []) if isinstance(data, dict) else data
                                
                                chain_txs = []
                                for tx in raw_txs[:100]:
                                    try:
                                        processed_tx = {
                                            'hash': tx.get('transaction_hash', ''),
                                            'block_number': int(tx.get('block_number', 0)),
                                            'timestamp': tx.get('block_timestamp', '')[:10] if tx.get('block_timestamp') else '',
                                            'from_address': tx.get('from_address', ''),
                                            'to_address': tx.get('to_address', ''),
                                            'value': float(tx.get('value', 0)) / (10 ** int(tx.get('token_decimals', 18))),
                                            'token_symbol': tx.get('token_symbol', get_native_token_for_chain(chain)),
                                            'token_address': tx.get('address', ''),
                                            'chain': chain,
                                            'transaction_type': 'token' if tx.get('address') else 'native'
                                        }
                                        chain_txs.append(processed_tx)
                                        all_transactions.append(processed_tx)
                                    except Exception as e:
                                        logger.warning(f"Error processing {chain} transaction: {e}")
                                        continue
                                
                                chain_transactions[chain] = len(chain_txs)
                                logger.info(f"SUCCESS: Processed {len(chain_txs)} {chain} transactions")
                            else:
                                logger.warning(f"WARNING: {chain} API returned {response.status}")
                                chain_transactions[chain] = 0
                    except Exception as e:
                        logger.warning(f"WARNING: Failed to fetch {chain} data: {e}")
                        chain_transactions[chain] = 0
                
                await asyncio.sleep(0.2)  # Rate limiting
                
            except Exception as e:
                logger.error(f"ERROR: Error processing {chain}: {e}")
                analytics_data['errors_encountered'].append(f"Chain {chain}: {str(e)}")
                continue

        # ⚡ FIXED: Process aggregated analytics data
        if all_transactions:
            analytics_data['total_transactions'] = len(all_transactions)
            analytics_data['chains_activity'] = chain_transactions
            analytics_data['chains_processed'] = list(chain_transactions.keys())
            
            # Calculate token trading stats
            token_stats = {}
            date_frequency = {}
            
            for tx in all_transactions:
                # Token trading statistics
                symbol = tx.get('token_symbol', 'UNKNOWN')
                if symbol and symbol != 'UNKNOWN':
                    if symbol not in token_stats:
                        token_stats[symbol] = {
                            'symbol': symbol,
                            'trade_count': 0,
                            'volume': 0.0,
                            'volume_usd': 0.0,
                            'chain': tx.get('chain', 'unknown')
                        }
                    
                    token_stats[symbol]['trade_count'] += 1
                    token_stats[symbol]['volume'] += tx.get('value', 0)
                    
                    # ⚡ SIMPLE: USD volume estimation for native tokens
                    if symbol in ['ETH', 'BNB', 'MATIC', 'AVAX']:
                        estimated_prices = {'ETH': 3400, 'BNB': 630, 'MATIC': 0.4, 'AVAX': 17}
                        price = estimated_prices.get(symbol, 0)
                        if price > 0:
                            token_stats[symbol]['volume_usd'] += tx.get('value', 0) * price
                
                # Date frequency
                if tx.get('timestamp'):
                    date_str = tx['timestamp']
                    if date_str:
                        date_frequency[date_str] = date_frequency.get(date_str, 0) + 1
            
            analytics_data['unique_tokens_traded'] = len(token_stats)
            analytics_data['total_volume_usd'] = sum(token.get('volume_usd', 0) for token in token_stats.values())
            analytics_data['transaction_frequency'] = date_frequency
            
            # Sort most traded tokens
            sorted_tokens = sorted(token_stats.values(), key=lambda x: x.get('trade_count', 0), reverse=True)
            analytics_data['most_traded_tokens'] = sorted_tokens[:10]
            
            # ⚡ NEW: Chain-specific data if selected
            if selected_chain:
                chain_specific_txs = [tx for tx in all_transactions if tx.get('chain') == selected_chain]
                if chain_specific_txs:
                    analytics_data['chain_specific_data'] = {
                        'chain': selected_chain,
                        'total_transactions': len(chain_specific_txs),
                        'transactions': chain_specific_txs[:50],  # Latest 50 for selected chain
                        'native_token': get_native_token_for_chain(selected_chain)
                    }
        
        logger.info(f"SUCCESS: Multi-chain analytics for {wallet_address}: {analytics_data['total_transactions']} total txs across {len(chain_transactions)} chains")
        
        return analytics_data
        
    except Exception as e:
        logger.error(f"ERROR: Multi-chain analytics failed: {str(e)}")
        analytics_data['errors_encountered'].append(f"General error: {str(e)}")
        return analytics_data

# ⚡ HELPER: Get native token for chain
def get_native_token_for_chain(chain: str) -> str:
    """Get native token symbol untuk chain tertentu"""
    chain_native_map = {
        'eth': 'ETH',
        'ethereum': 'ETH', 
        'bsc': 'BNB',
        'polygon': 'MATIC',
        'avalanche': 'AVAX'
    }
    return chain_native_map.get(chain.lower(), 'ETH')

# ⚡ FIXED: Prioritize tokens yang SUDAH ADA USD VALUE dan native tokens
def prioritize_native_tokens_only(tokens: List[Dict]) -> List[Dict]:
    """⚡ ENHANCED: Prioritize native tokens only untuk speed dan reliability"""
    
    # Known native tokens (prioritas utama)
    native_symbols = {
        'ETH', 'BNB', 'MATIC', 'AVAX', 'AVALANCHE',
        'WETH'  # Wrapped ETH juga penting
    }
    
    # ⚡ STEP 1: Filter hanya native tokens dan tokens dengan USD value
    native_tokens = []
    valued_tokens = []
    
    for token in tokens:
        symbol = token.get('symbol', '').upper()
        balance = token.get('balance', 0)
        
        # Skip spam tokens
        if is_spam_token(token.get('name', ''), symbol, balance):
            continue
            
        # Skip very small balances
        if balance < 0.000001:
            continue
        
        # ⚡ PRIORITY 1: Native tokens (always include)
        if symbol in native_symbols:
            native_tokens.append(token)
        # ⚡ PRIORITY 2: Tokens yang sudah ada usd_value dari Moralis
        elif token.get('usd_value') is not None and token.get('usd_value') > 0:
            valued_tokens.append(token)
    
    # Sort native tokens by balance (descending)
    native_tokens.sort(key=lambda x: x.get('balance', 0), reverse=True)
    
    # Sort valued tokens by USD value (descending)  
    valued_tokens.sort(key=lambda x: x.get('usd_value', 0), reverse=True)
    
    # ⚡ RETURN: Native tokens first, then valued tokens (up to limit)
    result = native_tokens + valued_tokens[:10]  # Max 10 additional valued tokens
    
    logger.info(f"SUCCESS: Prioritized {len(result)} tokens: {len(native_tokens)} native + {len(valued_tokens[:10])} valued tokens")
    
    return result[:MAX_TOKENS_TO_SHOW]

# ⚡ ENHANCED: Native token price fetching dengan BNB support
async def get_native_token_price(session: aiohttp.ClientSession, token_symbol: str, token_address: str = None, chain: str = None) -> float:
    """
    ⚡ FIXED: Native token price fetching dengan BNB mapping yang benar
    """
    coingecko_api_key = os.environ.get('COINGECKO_API_KEY', '')
    base_url = "https://api.coingecko.com/api/v3"
    
    # Build headers
    headers = {}
    if coingecko_api_key and coingecko_api_key not in ['CG-CC***', 'YOUR-API-KEY-HERE']:
        headers['x-cg-demo-api-key'] = coingecko_api_key
    
    try:
        # ⚡ FIXED: Native token mapping dengan BNB yang benar
        native_symbols = {
            'ETH': 'ethereum',
            'BNB': 'binancecoin',  # ⚡ FIXED: BNB mapping yang benar
            'MATIC': 'matic-network',
            'AVAX': 'avalanche-2',
            'AVALANCHE': 'avalanche-2',
            'WETH': 'ethereum'  # WETH menggunakan ETH price
        }
        
        symbol_upper = token_symbol.upper()
        if symbol_upper in native_symbols:
            coin_id = native_symbols[symbol_upper]
            price_url = f"{base_url}/simple/price"
            params = {
                'ids': coin_id,
                'vs_currencies': 'usd'
            }
            
            async with session.get(price_url, params=params, headers=headers, timeout=aiohttp.ClientTimeout(total=5)) as response:
                if response.status == 200:
                    data = await response.json()
                    if coin_id in data and 'usd' in data[coin_id]:
                        price = float(data[coin_id]['usd'])
                        logger.info(f"SUCCESS: Native price for {token_symbol}: ${price:.8f}")
                        return price
                else:
                    logger.warning(f"Warning: CoinGecko API returned status {response.status} for {token_symbol}")
        
        logger.info(f"SKIPPED: {token_symbol} is not a native token")
        return 0.0
        
    except Exception as e:
        logger.warning(f"WARNING: Price fetch error for {token_symbol}: {str(e)}")
        return 0.0

# ⚡ ENHANCED: Fetch prices only for native tokens untuk speed
async def fetch_native_token_prices_only(session: aiohttp.ClientSession, tokens: List[Dict]) -> Dict[str, float]:
    """
    ⚡ OPTIMIZED: Hanya fetch price untuk native tokens untuk speed maksimal
    """
    try:
        # ⚡ STEP 1: Filter hanya native tokens
        native_tokens = prioritize_native_tokens_only(tokens)
        
        logger.info(f"SUCCESS: Processing {len(native_tokens)} tokens (focus on native for speed)")
        
        # ⚡ STEP 2: Separate tokens berdasarkan existing USD value
        tokens_need_price = []
        existing_prices = {}
        
        for token in native_tokens:
            symbol = token.get('symbol', '')
            if symbol:
                # ⚡ Use existing USD value if available
                if token.get('usd_value') is not None and token.get('usd_value') > 0:
                    balance = token.get('balance', 0)
                    if balance > 0:
                        existing_prices[symbol] = token['usd_value'] / balance
                else:
                    # Need to fetch price (focus on native tokens only)
                    tokens_need_price.append(token)
        
        logger.info(f"CHECKED: {len(existing_prices)} tokens already have USD value, {len(tokens_need_price)} native tokens need price fetch")
        
        # ⚡ STEP 3: Fetch prices only for native tokens that need it
        if tokens_need_price:
            logger.info(f"FINDING: Fetching prices for {len(tokens_need_price)} native tokens")
            
            for token in tokens_need_price:
                symbol = token.get('symbol', '')
                address = token.get('address', '')
                chain = token.get('chain', '')
                
                if symbol and symbol not in existing_prices:
                    try:
                        price = await get_native_token_price(session, symbol, address, chain)
                        if price > 0:
                            existing_prices[symbol] = price
                        # ⚡ Minimal delay for native tokens
                        await asyncio.sleep(0.5)  # Reduced delay for native tokens
                    except Exception as e:
                        logger.warning(f"Warning: Failed to fetch price for native token {symbol}: {str(e)}")
        
        logger.info(f"SUCCESS: Final prices collected: {len(existing_prices)} tokens")
        return existing_prices
        
    except Exception as e:
        logger.error(f"Error in native token price fetching: {str(e)}")
        return {}

# ⚡ ENHANCED: Fetch Moralis portfolio dengan native token focus
async def fetch_moralis_portfolio(session: aiohttp.ClientSession, wallet_address: str) -> Dict:
    """⚡ OPTIMIZED: Fetch portfolio dengan focus pada native tokens untuk speed"""
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
        
        # Collect all tokens
        all_tokens = []
        total_tokens_found = 0
        
        # Fetch data dari multiple chains
        for chain in config['chains']:
            try:
                # Native balance (prioritas utama)
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
                            
                            # Include semua native balances (even small ones)
                            if balance_eth > 0:
                                all_tokens.append({
                                    'symbol': symbol,
                                    'address': '0x0',
                                    'chain': chain,
                                    'balance': balance_eth,
                                    'usd_value': None
                                })
                                
                                portfolio_data['native_balances'].append({
                                    'token_address': '0x0',
                                    'token_name': symbol,
                                    'token_symbol': symbol,
                                    'balance': balance_eth,
                                    'balance_raw': str(balance_wei),
                                    'decimals': 18,
                                    'chain': chain,
                                    'usd_value': None,
                                    'is_spam': False
                                })
                
                # Token balances (dengan filter spam yang ketat)
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
                            
                            if balance > 0:
                                if not is_spam:
                                    # Check existing USD value from Moralis
                                    existing_usd_value = token.get('usd_price')
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
                
                await asyncio.sleep(0.1)
                
            except Exception as e:
                logger.warning(f"Error fetching {chain} data: {str(e)}")
                continue
        
        logger.info(f"CHECKED: Found {total_tokens_found} total tokens, filtered {portfolio_data['filtered_tokens_count']} spam tokens")
        
        # ⚡ OPTIMIZED: Focus pada native tokens untuk price fetching
        if all_tokens:
            token_prices = await fetch_native_token_prices_only(session, all_tokens)
            
            # Calculate USD values
            total_usd_value = 0.0
            
            # Update native balances dengan USD values
            for balance in portfolio_data['native_balances']:
                symbol = balance['token_symbol']
                if symbol in token_prices:
                    usd_value = balance['balance'] * token_prices[symbol]
                    
                    # ⚡ Validate USD value
                    if usd_value > 0 and usd_value < 1e10:
                        balance['usd_value'] = usd_value
                        total_usd_value += usd_value
            
            # Update token balances dengan USD values (focus on valuable tokens)
            valid_tokens_count = 0
            for token in portfolio_data['token_balances']:
                if not token.get('is_spam', False):
                    # Use existing USD value if available
                    if token.get('usd_value') is not None and token['usd_value'] > 0:
                        total_usd_value += token['usd_value']
                        valid_tokens_count += 1
                    else:
                        # Try to get price from fetched prices (native tokens only)
                        symbol = token['token_symbol']
                        if symbol in token_prices:
                            usd_value = token['balance'] * token_prices[symbol]
                            
                            # ⚡ Validate USD value
                            if usd_value > 0 and usd_value < 1e10:
                                token['usd_value'] = usd_value
                                total_usd_value += usd_value
                                valid_tokens_count += 1
            
            portfolio_data['total_usd_value'] = total_usd_value
            
            logger.info(f"SUCCESS: Native-focused portfolio: {len(portfolio_data['native_balances'])} native + {valid_tokens_count} valued tokens")
            logger.info(f"PRICE: Total USD: ${total_usd_value:.8f} (native-focused calculation)")
            logger.info(f"BANNED: Filtered {portfolio_data['filtered_tokens_count']} spam tokens")
        
        return portfolio_data
        
    except Exception as e:
        logger.error(f"Error fetching Moralis portfolio: {str(e)}")
        return {'native_balances': [], 'token_balances': [], 'total_usd_value': 0.0, 'filtered_tokens_count': 0}

# Keep existing helper functions
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

# ⚡ FIXED: Transaction fetching dengan proper chain filtering
async def fetch_onchain_transactions(session: aiohttp.ClientSession, wallet_address: str, limit: int = 50, selected_chains: List[str] = None) -> List[Dict]:
    """⚡ FIXED: Fetch transaksi onchain dengan proper chain filtering"""
    try:
        all_transactions = []
        target_chains = selected_chains or ['eth', 'bsc', 'polygon', 'avalanche']
        
        logger.info(f"TRANSACTIONS: Fetching from chains: {target_chains}")
        
        for chain in target_chains:
            try:
                chain_transactions = []
                
                if chain in ['eth', 'ethereum']:
                    # ⚡ FIXED: Use working Etherscan approach for ETH
                    eth_txs = await fetch_ethereum_data(session, wallet_address, 'transactions')
                    
                    for tx in eth_txs[:limit]:
                        try:
                            processed_tx = {
                                'tx_hash': tx.get('hash', ''),
                                'block_number': int(tx.get('blockNumber', 0)),
                                'timestamp': datetime.fromtimestamp(int(tx.get('timeStamp', 0))),
                                'from_address': tx.get('from', ''),
                                'to_address': tx.get('to', ''),
                                'value': float(tx.get('value', 0)) / 1e18,
                                'value_raw': tx.get('value', '0'),
                                'gas_used': int(tx.get('gasUsed', 0)),
                                'gas_price': tx.get('gasPrice', '0'),
                                'token_symbol': 'ETH',
                                'token_address': '',
                                'transaction_type': 'native',
                                'chain': 'ethereum',
                                'status': 'success' if tx.get('txreceipt_status') == '1' else 'failed'
                            }
                            chain_transactions.append(processed_tx)
                        except Exception as e:
                            logger.warning(f"WARNING: Error processing ETH transaction: {e}")
                            continue
                    
                    logger.info(f"SUCCESS: Fetched {len(chain_transactions)} ETH transactions")
                
                else:
                    # ⚡ FIXED: Use Moralis for other chains with proper error handling
                    config = BLOCKCHAIN_APIS['moralis']
                    headers = {
                        'X-API-Key': config['api_key'],
                        'Content-Type': 'application/json'
                    }
                    
                    # Try ERC20 transfers first
                    transfers_url = f"{config['api_url']}/{wallet_address}/erc20/transfers"
                    params = {
                        'chain': chain,
                        'limit': limit,
                        'order': 'DESC'
                    }
                    
                    async with session.get(transfers_url, headers=headers, params=params,
                                         timeout=aiohttp.ClientTimeout(total=15)) as response:
                        if response.status == 200:
                            data = await response.json()
                            raw_transfers = data.get('result', []) if isinstance(data, dict) else data
                            
                            for transfer in raw_transfers:
                                try:
                                    processed_tx = {
                                        'tx_hash': transfer.get('transaction_hash', ''),
                                        'block_number': int(transfer.get('block_number', 0)),
                                        'timestamp': datetime.fromisoformat(transfer.get('block_timestamp', '').replace('Z', '+00:00')) if transfer.get('block_timestamp') else datetime.now(),
                                        'from_address': transfer.get('from_address', ''),
                                        'to_address': transfer.get('to_address', ''),
                                        'value': float(transfer.get('value', 0)) / (10 ** int(transfer.get('token_decimals', 18))),
                                        'value_raw': transfer.get('value', '0'),
                                        'gas_used': 0,  # Not available in transfers
                                        'gas_price': '0',
                                        'token_symbol': transfer.get('token_symbol', get_native_token_for_chain(chain)),
                                        'token_address': transfer.get('address', ''),
                                        'transaction_type': 'token',
                                        'chain': chain,
                                        'status': 'success'  # Transfers are generally successful
                                    }
                                    chain_transactions.append(processed_tx)
                                except Exception as e:
                                    logger.warning(f"WARNING: Error processing {chain} transfer: {e}")
                                    continue
                            
                            logger.info(f"SUCCESS: Fetched {len(chain_transactions)} {chain} transfers")
                        
                        else:
                            logger.warning(f"WARNING: {chain} transfers API returned {response.status}")
                    
                    # Also try to get native transactions untuk chain ini
                    try:
                        native_url = f"{config['api_url']}/{wallet_address}"
                        native_params = {'chain': chain}
                        
                        async with session.get(native_url, headers=headers, params=native_params,
                                             timeout=aiohttp.ClientTimeout(total=10)) as response:
                            if response.status == 200:
                                native_data = await response.json()
                                native_balance = native_data.get('balance', '0')
                                
                                # If there's native balance, assume there are native transactions
                                if int(native_balance) > 0:
                                    # Add a dummy native transaction untuk display purposes
                                    native_tx = {
                                        'tx_hash': f'native_{chain}_latest',
                                        'block_number': 0,
                                        'timestamp': datetime.now(),
                                        'from_address': '',
                                        'to_address': wallet_address,
                                        'value': float(native_balance) / 1e18,
                                        'value_raw': native_balance,
                                        'gas_used': 0,
                                        'gas_price': '0',
                                        'token_symbol': get_native_token_for_chain(chain),
                                        'token_address': '',
                                        'transaction_type': 'native',
                                        'chain': chain,
                                        'status': 'success'
                                    }
                                    chain_transactions.insert(0, native_tx)
                                    logger.info(f"INFO: Added native {chain} transaction")
                    except Exception as e:
                        logger.warning(f"WARNING: Could not fetch native {chain} data: {e}")
                
                # Add chain transactions to overall list
                all_transactions.extend(chain_transactions)
                await asyncio.sleep(0.3)  # Rate limiting between chains
                
            except Exception as e:
                logger.error(f"ERROR: Failed to fetch {chain} transactions: {e}")
                continue
        
        # ⚡ Sort by timestamp descending
        all_transactions.sort(key=lambda x: x.get('timestamp', datetime.min), reverse=True)
        
        # ⚡ Apply final limit
        final_transactions = all_transactions[:limit]
        
        logger.info(f"SUCCESS: Total fetched {len(final_transactions)} transactions from {len(target_chains)} chains")
        
        return final_transactions
        
    except Exception as e:
        logger.error(f"ERROR: Transaction fetching failed: {str(e)}")
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

# API Configuration
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

# API Endpoints
@router.get("/portfolio/{wallet_address}")
async def get_wallet_portfolio(
    wallet_address: str = Path(..., description="Wallet address"),
    chains: Optional[List[str]] = Query(None, description="Chains to scan (eth, bsc, polygon)")
) -> WalletPortfolio:
    """⚡ OPTIMIZED: Portfolio dengan NATIVE-FOCUSED calculations dan spam filtering yang ketat"""
    
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
            # ⚡ Fetch portfolio menggunakan native-focused method
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
            
            logger.info(f"OPTIMIZED: NATIVE-FOCUSED portfolio for {wallet_address}: {len(portfolio.native_balances)} native + {len(portfolio.token_balances)} tokens, USD Total: ${portfolio.total_usd_value:.8f}")
            
            return portfolio
    
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"Error getting wallet portfolio: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

# ⚡ FIXED: Update transactions endpoint dengan proper chain filtering
@router.get("/transactions/{wallet_address}")
async def get_wallet_transactions(
    wallet_address: str = Path(..., description="Wallet address"),
    limit: int = Query(50, ge=1, le=200, description="Number of transactions to fetch"),
    chains: Optional[List[str]] = Query(None, description="Chains to scan")
) -> List[OnchainTransaction]:
    """⚡ FIXED: Get transactions dengan proper chain filtering"""
    
    # ⚡ FIXED: Build cache key dengan proper chains
    chains_str = ','.join(sorted(chains)) if chains else 'all'
    cache_key = f"transactions_fixed_v5_{wallet_address}_{limit}_{chains_str}"
    
    # Check cache
    if cache_key in _onchain_cache:
        cache_entry = _onchain_cache[cache_key]
        if datetime.now() < cache_entry['expires']:
            logger.info(f"CACHE: Returning cached transactions for {wallet_address}")
            return cache_entry['data']
    
    try:
        # ⚡ VALIDASI: Pastikan wallet address format valid
        if not wallet_address or len(wallet_address) < 40:
            raise HTTPException(status_code=400, detail="Invalid wallet address format")
        
        async with aiohttp.ClientSession() as session:
            # ⚡ FIXED: Use fixed transaction fetching
            transactions_data = await fetch_onchain_transactions(session, wallet_address, limit, chains)
            
            # Convert to response model
            transactions = []
            for tx_data in transactions_data:
                try:
                    tx = OnchainTransaction(
                        tx_hash=tx_data.get('tx_hash', ''),
                        block_number=tx_data.get('block_number', 0),
                        timestamp=tx_data.get('timestamp', datetime.now()),
                        from_address=tx_data.get('from_address', ''),
                        to_address=tx_data.get('to_address', ''),
                        value=tx_data.get('value', 0.0),
                        value_raw=tx_data.get('value_raw', '0'),
                        gas_used=tx_data.get('gas_used', 0),
                        gas_price=tx_data.get('gas_price', '0'),
                        token_symbol=tx_data.get('token_symbol'),
                        token_address=tx_data.get('token_address'),
                        transaction_type=tx_data.get('transaction_type', 'unknown'),
                        chain=tx_data.get('chain', 'unknown'),
                        status=tx_data.get('status', 'unknown')
                    )
                    transactions.append(tx)
                except Exception as e:
                    logger.warning(f"WARNING: Error creating transaction object: {e}")
                    continue
            
            # Cache hasil
            _onchain_cache[cache_key] = {
                'data': transactions,
                'expires': datetime.now() + timedelta(seconds=_cache_ttl)
            }
            
            chains_info = f" dari {chains}" if chains else " dari semua chains"
            logger.info(f"SUCCESS: Fetched {len(transactions)} transactions untuk {wallet_address}{chains_info}")
            
            return transactions
    
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"ERROR: Transaction endpoint failed: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

# ⚡ FIXED: Update the existing analytics endpoint
@router.get("/analytics/{wallet_address}")
async def get_wallet_analytics(
    wallet_address: str = Path(..., description="Wallet address"),
    days: int = Query(30, ge=1, le=365, description="Days to analyze"),
    chain: Optional[str] = Query(None, description="Specific chain to focus on")
) -> OnchainAnalytics:
    """⚡ FIXED: Analytics dengan proper multi-chain support"""
    
    cache_key = f"analytics_fixed_v5_{wallet_address}_{days}_{chain or 'all'}"
    
    # Check cache
    if cache_key in _onchain_cache:
        cache_entry = _onchain_cache[cache_key]
        if datetime.now() < cache_entry['expires']:
            logger.info(f"CACHE: Returning cached analytics for {wallet_address}")
            return cache_entry['data']
    
    try:
        # ⚡ VALIDASI: Pastikan wallet address format valid
        if not wallet_address or len(wallet_address) < 40:
            raise HTTPException(status_code=400, detail="Invalid wallet address format")
        
        async with aiohttp.ClientSession() as session:
            # ⚡ FIXED: Use fixed multi-chain analytics
            analytics_data = await get_onchain_analytics(session, wallet_address, chain)
            
            # ⚡ FIXED: Create response object with proper data
            analytics = OnchainAnalytics(
                wallet_address=analytics_data['wallet_address'],
                total_transactions=analytics_data['total_transactions'],
                unique_tokens_traded=analytics_data['unique_tokens_traded'],
                total_volume_usd=analytics_data['total_volume_usd'],
                most_traded_tokens=analytics_data['most_traded_tokens'],
                transaction_frequency=analytics_data['transaction_frequency'],
                chains_activity=analytics_data['chains_activity'],
                selected_chain=analytics_data.get('selected_chain'),
                chain_specific_data=analytics_data.get('chain_specific_data'),
                cross_chain_volume=analytics_data.get('cross_chain_volume', 0.0),
                chain_dominance=analytics_data.get('chain_dominance', {}),
                diversification_score=analytics_data.get('diversification_score', 0.0),
                chains_processed=analytics_data.get('chains_processed', []),
                errors_encountered=analytics_data.get('errors_encountered', [])
            )
            
            # Cache hasil dengan TTL yang sesuai
            cache_ttl_minutes = 10 if chain else 15  # Shorter cache for specific chain
            _onchain_cache[cache_key] = {
                'data': analytics,
                'expires': datetime.now() + timedelta(minutes=cache_ttl_minutes)
            }
            
            logger.info(f"SUCCESS: Fixed analytics for {wallet_address} (chain: {chain or 'all'}): {analytics.total_transactions} txs, {analytics.unique_tokens_traded} tokens")
            
            return analytics
    
    except HTTPException as he:
        raise he
    except Exception as e:
        logger.error(f"ERROR: Analytics endpoint failed: {str(e)}")
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
                'native_token_focus': 'enabled',
                'spam_detection': 'enhanced',
                'price_fetching': 'native_only',
                'max_tokens_to_show': MAX_TOKENS_TO_SHOW,
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
        
        logger.info(f"Native-focused blockchain health check: Moralis={status['moralis_api']}, CoinGecko={status['coingecko_api']}, Cache={status['cache_entries']} entries")
        
        return status
    
    except Exception as e:
        logger.error(f"Health check error: {str(e)}")
        return {"status": "error", "message": str(e), "timestamp": datetime.now()}