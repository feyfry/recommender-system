import os
import logging
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
import asyncio
import aiohttp
from fastapi import APIRouter, HTTPException, Query, Path
from pydantic import BaseModel, Field
import time

# Setup router
router = APIRouter(
    prefix="/blockchain",
    tags=["blockchain data"],
    responses={404: {"description": "Not found"}},
)

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# API Configuration - UPDATED dengan API keys dari .env
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

# ⚡ BARU: Price mapping untuk native tokens
NATIVE_TOKEN_PRICES = {
    'ETH': 'ethereum',
    'BNB': 'binancecoin', 
    'MATIC': 'matic-network',
    'AVALANCHE': 'avalanche-2',
    'AVAX': 'avalanche-2'
}

# Cache untuk menyimpan data onchain
_onchain_cache = {}
_price_cache = {}  # ⚡ BARU: Cache untuk harga
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

class OnchainAnalytics(BaseModel):
    wallet_address: str
    total_transactions: int
    unique_tokens_traded: int
    total_volume_usd: float
    most_traded_tokens: List[Dict[str, Any]]
    transaction_frequency: Dict[str, int]
    profit_loss_estimate: Optional[float] = None
    chains_activity: Dict[str, int]

# ⚡ BARU: Helper untuk mendapatkan harga dari CoinGecko
async def fetch_token_prices(session: aiohttp.ClientSession, token_symbols: List[str]) -> Dict[str, float]:
    """Fetch token prices dari CoinGecko API"""
    try:
        # Check cache dulu
        cache_key = "token_prices_" + "_".join(sorted(token_symbols))
        if cache_key in _price_cache:
            cache_entry = _price_cache[cache_key]
            if datetime.now() < cache_entry['expires']:
                return cache_entry['data']
        
        coingecko_api_key = os.environ.get('COINGECKO_API_KEY', '')
        
        # Map symbols ke CoinGecko IDs
        token_ids = []
        symbol_to_id = {}
        
        for symbol in token_symbols:
            if symbol in NATIVE_TOKEN_PRICES:
                coingecko_id = NATIVE_TOKEN_PRICES[symbol]
                token_ids.append(coingecko_id)
                symbol_to_id[symbol] = coingecko_id
        
        if not token_ids:
            return {}
        
        # Build URL dengan atau tanpa API key
        ids_str = ','.join(token_ids)
        if coingecko_api_key and coingecko_api_key != 'CG-CC***':
            url = f"https://api.coingecko.com/api/v3/simple/price?ids={ids_str}&vs_currencies=usd&x_cg_demo_api_key={coingecko_api_key}"
        else:
            # Gunakan public API tanpa key (rate limited)
            url = f"https://api.coingecko.com/api/v3/simple/price?ids={ids_str}&vs_currencies=usd"
        
        async with session.get(url) as response:
            if response.status == 200:
                price_data = await response.json()
                
                # Convert back to symbol-based mapping
                symbol_prices = {}
                for symbol, coingecko_id in symbol_to_id.items():
                    if coingecko_id in price_data and 'usd' in price_data[coingecko_id]:
                        symbol_prices[symbol] = price_data[coingecko_id]['usd']
                
                # Cache hasil
                _price_cache[cache_key] = {
                    'data': symbol_prices,
                    'expires': datetime.now() + timedelta(minutes=5)
                }
                
                logger.info(f"Fetched prices for {len(symbol_prices)} tokens")
                return symbol_prices
            else:
                logger.warning(f"CoinGecko API returned status {response.status}")
                return {}
                
    except Exception as e:
        logger.error(f"Error fetching token prices: {str(e)}")
        return {}

# Helper Functions
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

async def fetch_moralis_portfolio(session: aiohttp.ClientSession, wallet_address: str) -> Dict:
    """⚡ ENHANCED: Fetch portfolio dengan USD values calculation"""
    try:
        config = BLOCKCHAIN_APIS['moralis']
        headers = {
            'X-API-Key': config['api_key'],
            'Content-Type': 'application/json'
        }
        
        portfolio_data = {
            'native_balances': [],
            'token_balances': [],
            'total_usd_value': 0.0
        }
        
        # Collect semua token symbols untuk price lookup
        all_symbols = set()
        
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
                                'avalanche': 'AVALANCHE'
                            }
                            symbol = chain_symbol_map.get(chain, chain.upper())
                            all_symbols.add(symbol)
                            
                            portfolio_data['native_balances'].append({
                                'token_address': '0x0',
                                'token_name': symbol,
                                'token_symbol': symbol,
                                'balance': balance_eth,
                                'balance_raw': str(balance_wei),
                                'decimals': 18,
                                'chain': chain,
                                'usd_value': None  # Will be calculated later
                            })
                
                # Token balances
                tokens_url = f"{config['api_url']}/{wallet_address}/erc20?chain={chain}"
                async with session.get(tokens_url, headers=headers) as response:
                    if response.status == 200:
                        tokens_data = await response.json()
                        for token in tokens_data:
                            balance_raw = int(token.get('balance', 0))
                            decimals = int(token.get('decimals', 18))
                            balance = balance_raw / (10 ** decimals)
                            
                            if balance > 0:  # Only include non-zero balances
                                symbol = token.get('symbol', 'UNKNOWN')
                                all_symbols.add(symbol)
                                
                                portfolio_data['token_balances'].append({
                                    'token_address': token.get('token_address'),
                                    'token_name': token.get('name'),
                                    'token_symbol': symbol,
                                    'balance': balance,
                                    'balance_raw': str(balance_raw),
                                    'decimals': decimals,
                                    'chain': chain,
                                    'usd_value': None  # Will be calculated later
                                })
                
                # Small delay antara requests
                await asyncio.sleep(0.1)
                
            except Exception as e:
                logger.warning(f"Error fetching {chain} data: {str(e)}")
                continue
        
        # ⚡ BARU: Fetch prices untuk semua tokens
        token_prices = await fetch_token_prices(session, list(all_symbols))
        
        # ⚡ Calculate USD values
        total_usd_value = 0.0
        
        # Update native balances dengan USD values
        for balance in portfolio_data['native_balances']:
            symbol = balance['token_symbol']
            if symbol in token_prices:
                usd_value = balance['balance'] * token_prices[symbol]
                balance['usd_value'] = usd_value
                total_usd_value += usd_value
        
        # Update token balances dengan USD values  
        for token in portfolio_data['token_balances']:
            symbol = token['token_symbol']
            if symbol in token_prices:
                usd_value = token['balance'] * token_prices[symbol]
                token['usd_value'] = usd_value
                total_usd_value += usd_value
        
        portfolio_data['total_usd_value'] = total_usd_value
        
        logger.info(f"Portfolio calculation: {len(portfolio_data['native_balances'])} native + {len(portfolio_data['token_balances'])} tokens, Total USD: ${total_usd_value:.2f}")
        
        return portfolio_data
        
    except Exception as e:
        logger.error(f"Error fetching Moralis portfolio: {str(e)}")
        return {'native_balances': [], 'token_balances': [], 'total_usd_value': 0.0}

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
    """⚡ ENHANCED: Hitung analytics dengan USD volume calculation"""
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
        
        # ⚡ Get current ETH price untuk volume calculation
        eth_price = 3400.0  # Fallback price, akan di-update dari API
        
        for tx in transactions:
            chain = tx.get('chain', 'unknown')
            chain_activity[chain] = chain_activity.get(chain, 0) + 1
            
            # Frequency by date
            tx_date = tx['timestamp'].strftime('%Y-%m-%d')
            analytics['transaction_frequency'][tx_date] = analytics['transaction_frequency'].get(tx_date, 0) + 1
            
            # Token activity dengan USD volume
            token_symbol = tx.get('token_symbol', 'ETH')
            if token_symbol not in token_activity:
                token_activity[token_symbol] = {'count': 0, 'volume': 0.0, 'volume_usd': 0.0}
            
            token_activity[token_symbol]['count'] += 1
            token_value = tx.get('value', 0)
            token_activity[token_symbol]['volume'] += token_value
            
            # Calculate USD volume (simplified untuk ETH)
            if token_symbol == 'ETH':
                usd_volume = token_value * eth_price
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

# API Endpoints
@router.get("/portfolio/{wallet_address}")
async def get_wallet_portfolio(
    wallet_address: str = Path(..., description="Wallet address"),
    chains: Optional[List[str]] = Query(None, description="Chains to scan (eth, bsc, polygon)")
) -> WalletPortfolio:
    """⚡ ENHANCED: Portfolio dengan USD calculations"""
    
    cache_key = f"portfolio_{wallet_address}_{','.join(chains or ['all'])}"
    
    # Check cache
    if cache_key in _onchain_cache:
        cache_entry = _onchain_cache[cache_key]
        if datetime.now() < cache_entry['expires']:
            logger.info(f"Returning cached portfolio for {wallet_address}")
            return cache_entry['data']
    
    try:
        # ⚡ VALIDASI: Pastikan wallet address format valid
        if not wallet_address or len(wallet_address) < 40:
            raise HTTPException(status_code=400, detail="Invalid wallet address format")
        
        async with aiohttp.ClientSession() as session:
            # Fetch portfolio menggunakan Moralis (multi-chain) dengan USD calculation
            portfolio_data = await fetch_moralis_portfolio(session, wallet_address)
            
            # Create response
            portfolio = WalletPortfolio(
                wallet_address=wallet_address,
                total_usd_value=portfolio_data.get('total_usd_value', 0.0),
                native_balances=[TokenBalance(**balance) for balance in portfolio_data.get('native_balances', [])],
                token_balances=[TokenBalance(**balance) for balance in portfolio_data.get('token_balances', [])],
                last_updated=datetime.now(),
                chains_scanned=chains or ['eth', 'bsc', 'polygon']
            )
            
            # Cache hasil
            _onchain_cache[cache_key] = {
                'data': portfolio,
                'expires': datetime.now() + timedelta(seconds=_cache_ttl)
            }
            
            logger.info(f"Successfully fetched portfolio for {wallet_address}: {len(portfolio.native_balances)} native + {len(portfolio.token_balances)} tokens, USD Total: ${portfolio.total_usd_value:.2f}")
            
            return portfolio
    
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"Error getting wallet portfolio: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

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
    """⚡ ENHANCED: Analytics dengan USD volume calculation"""
    
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
            
            # Calculate analytics dengan USD volume
            analytics_data = calculate_portfolio_analytics(transactions_data, portfolio_data)
            analytics_data['wallet_address'] = wallet_address
            
            analytics = OnchainAnalytics(**analytics_data)
            
            # Cache hasil
            _onchain_cache[cache_key] = {
                'data': analytics,
                'expires': datetime.now() + timedelta(seconds=_cache_ttl * 2)  # Cache lebih lama
            }
            
            logger.info(f"Successfully calculated analytics for {wallet_address}: {analytics.total_transactions} txs, {analytics.unique_tokens_traded} tokens, USD Volume: ${analytics.total_volume_usd:.2f}")
            
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
    global _onchain_cache, _price_cache
    
    try:
        cache_size = len(_onchain_cache)
        price_cache_size = len(_price_cache)
        
        _onchain_cache = {}
        _price_cache = {}
        
        logger.info(f"Onchain cache cleared: {cache_size} entries + {price_cache_size} price entries")
        
        return {"message": f"Onchain cache cleared ({cache_size} entries, {price_cache_size} price entries)"}
    
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
            'coingecko_api': 'unknown',  # ⚡ BARU
            'cache_entries': len(_onchain_cache),
            'price_cache_entries': len(_price_cache),  # ⚡ BARU
            'timestamp': datetime.now(),
            'api_keys_configured': {
                'moralis': bool(os.environ.get('MORALIS_API_KEY')),
                'etherscan': bool(os.environ.get('ETHERSCAN_API_KEY')),
                'bscscan': bool(os.environ.get('BSCSCAN_API_KEY')),
                'polygonscan': bool(os.environ.get('POLYGONSCAN_API_KEY')),
                'coingecko': bool(os.environ.get('COINGECKO_API_KEY'))  # ⚡ BARU
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
                
                # ⚡ Test CoinGecko API
                coingecko_key = os.environ.get('COINGECKO_API_KEY')
                if coingecko_key and coingecko_key not in ['CG-CC***', 'YOUR-API-KEY-HERE']:
                    test_url = f"https://api.coingecko.com/api/v3/simple/price?ids=ethereum&vs_currencies=usd&x_cg_demo_api_key={coingecko_key}"
                else:
                    test_url = "https://api.coingecko.com/api/v3/simple/price?ids=ethereum&vs_currencies=usd"
                
                async with session.get(test_url, timeout=aiohttp.ClientTimeout(total=5)) as response:
                    status['coingecko_api'] = 'healthy' if response.status == 200 else f'error_status_{response.status}'
                    
        except Exception as e:
            status['moralis_api'] = f'error: {str(e)}'
            status['coingecko_api'] = f'error: {str(e)}'
        
        logger.info(f"Blockchain health check: Moralis={status['moralis_api']}, CoinGecko={status['coingecko_api']}, Cache={status['cache_entries']} entries")
        
        return status
    
    except Exception as e:
        logger.error(f"Health check error: {str(e)}")
        return {"status": "error", "message": str(e), "timestamp": datetime.now()}