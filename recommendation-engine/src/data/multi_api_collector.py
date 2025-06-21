import os
import requests
import time
import logging
from typing import Dict, List, Optional, Union, Any
from datetime import datetime, timedelta
from dataclasses import dataclass
from concurrent.futures import ThreadPoolExecutor, as_completed
import json

# Try to import pandas, fallback jika tidak ada
try:
    import pandas as pd
    PANDAS_AVAILABLE = True
except ImportError:
    logging.warning("Pandas not available - some features will be limited")
    pd = None
    PANDAS_AVAILABLE = False

# PERBAIKAN: Setup logging dengan encoding yang aman untuk Windows
class SafeFormatter(logging.Formatter):
    def format(self, record):
        # Remove emoji characters untuk Windows compatibility
        msg = super().format(record)
        # Replace emoji dengan text equivalent
        msg = msg.replace('🔍', '[SEARCH]')
        msg = msg.replace('📊', '[DATA]')
        msg = msg.replace('✅', '[SUCCESS]')
        msg = msg.replace('❌', '[ERROR]')
        msg = msg.replace('🎉', '[COMPLETE]')
        msg = msg.replace('🔄', '[RETRY]')
        return msg

# Setup logging with safe formatter
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Apply safe formatter to all handlers
for handler in logger.handlers:
    handler.setFormatter(SafeFormatter())

@dataclass
class APIConfig:
    name: str
    base_url: str
    api_key: str
    rate_limit: int  # requests per minute
    cost: str
    reliability: str
    supported_chains: List[str]

@dataclass
class TransactionData:
    tx_hash: str
    from_address: str
    to_address: str
    value: float
    token_symbol: str
    token_address: str
    block_number: int
    timestamp: datetime
    gas_used: int
    gas_price: float
    transaction_type: str  # 'buy', 'sell', 'transfer'
    chain: str
    project_id: Optional[str] = None
    
    # Additional fields untuk Laravel compatibility
    def to_laravel_format(self) -> Dict[str, Any]:
        """Convert to format expected by Laravel Transaction model"""
        return {
            'tx_hash': self.tx_hash,
            'from_address': self.from_address,
            'to_address': self.to_address,
            'value': self.value,
            'token_symbol': self.token_symbol,
            'token_address': self.token_address,
            'block_number': self.block_number,
            'timestamp': self.timestamp.isoformat(),
            'gas_used': self.gas_used,
            'gas_price': self.gas_price,
            'transaction_type': self.transaction_type,
            'chain': self.chain,
            'project_id': self.project_id,
            'source': 'api_sync',
            'is_verified': True,
            'raw_data': {
                'api_source': 'multi_api_collector',
                'collected_at': datetime.now().isoformat(),
                'original_data': {
                    'tx_hash': self.tx_hash,
                    'from_address': self.from_address,
                    'to_address': self.to_address,
                    'value': self.value,
                    'token_symbol': self.token_symbol,
                    'token_address': self.token_address,
                    'block_number': self.block_number,
                    'timestamp': self.timestamp.isoformat(),
                    'gas_used': self.gas_used,
                    'gas_price': self.gas_price,
                    'transaction_type': self.transaction_type,
                    'chain': self.chain
                }
            }
        }

class MultiAPICollector:
    def __init__(self):
        self.apis = self._load_api_configs()
        self.session = requests.Session()
        self.rate_limits = {}  # Track rate limits per API
        
    def _load_api_configs(self) -> Dict[str, APIConfig]:
        """Load API configurations dengan environment check yang benar"""
        return {
            # Blockchain Transaction APIs
            'moralis': APIConfig(
                name='Moralis',
                base_url='https://deep-index.moralis.io/api/v2',
                api_key=os.getenv('MORALIS_API_KEY', ''),
                rate_limit=25,  # per second on free tier
                cost='FREE_TIER',
                reliability='HIGH',
                supported_chains=['eth', 'bsc', 'polygon', 'avalanche', 'fantom', 'cronos']
            ),
            'alchemy': APIConfig(
                name='Alchemy',
                base_url='https://eth-mainnet.g.alchemy.com/v2',
                api_key=os.getenv('ALCHEMY_API_KEY', ''),
                rate_limit=330,  # per second
                cost='FREE_TIER',
                reliability='HIGH',
                supported_chains=['eth', 'polygon', 'arbitrum', 'optimism']
            ),
            'etherscan': APIConfig(
                name='Etherscan',
                base_url='https://api.etherscan.io/api',
                api_key=os.getenv('ETHERSCAN_API_KEY', ''),
                rate_limit=5,  # per second
                cost='FREE',
                reliability='HIGH',
                supported_chains=['eth']
            ),
            'bscscan': APIConfig(
                name='BSCScan',
                base_url='https://api.bscscan.com/api',
                api_key=os.getenv('BSCSCAN_API_KEY', ''),
                rate_limit=5,  # per second
                cost='FREE',
                reliability='HIGH',
                supported_chains=['bsc']
            ),
            'polygonscan': APIConfig(
                name='PolygonScan',
                base_url='https://api.polygonscan.com/api',
                api_key=os.getenv('POLYGONSCAN_API_KEY', ''),
                rate_limit=5,  # per second
                cost='FREE',
                reliability='HIGH',
                supported_chains=['polygon']
            ),
            
            # Price Data APIs (FREE & UNLIMITED)
            'binance': APIConfig(
                name='Binance Public API',
                base_url='https://api.binance.com/api/v3',
                api_key='',  # No API key needed for public endpoints
                rate_limit=1200,  # per minute
                cost='FREE',
                reliability='HIGH',
                supported_chains=['binance']
            ),
            'cryptocompare': APIConfig(
                name='CryptoCompare',
                base_url='https://min-api.cryptocompare.com/data',
                api_key=os.getenv('CRYPTOCOMPARE_API_KEY', ''),
                rate_limit=100,  # per hour free tier
                cost='FREE_TIER',
                reliability='MEDIUM',
                supported_chains=['multiple']
            ),
            'coinlore': APIConfig(
                name='CoinLore',
                base_url='https://api.coinlore.net/api',
                api_key='',  # No API key needed
                rate_limit=10000,  # per hour
                cost='FREE',
                reliability='MEDIUM',
                supported_chains=['multiple']
            ),
            'coingecko': APIConfig(
                name='CoinGecko',
                base_url='https://api.coingecko.com/api/v3',
                api_key=os.getenv('COINGECKO_API_KEY', ''),
                rate_limit=10,  # per minute on free tier
                cost='FREE_TIER',
                reliability='HIGH',
                supported_chains=['multiple']
            )
        }
    
    def _check_rate_limit(self, api_name: str) -> bool:
        """Check if we can make a request to this API"""
        now = time.time()
        if api_name not in self.rate_limits:
            self.rate_limits[api_name] = []
        
        # Remove old timestamps
        cutoff = now - 60  # Last minute
        self.rate_limits[api_name] = [
            timestamp for timestamp in self.rate_limits[api_name] 
            if timestamp > cutoff
        ]
        
        # Check if we're under the rate limit
        api_config = self.apis[api_name]
        if len(self.rate_limits[api_name]) < api_config.rate_limit:
            self.rate_limits[api_name].append(now)
            return True
        
        return False
    
    def _wait_for_rate_limit(self, api_name: str):
        """Wait until we can make a request"""
        while not self._check_rate_limit(api_name):
            time.sleep(1)

    def _safe_parse_value(self, value_str: str, decimals: int, token_symbol: str = "UNKNOWN") -> float:
        """
        PERBAIKAN: Safe parsing untuk token values dengan validation
        """
        try:
            if not value_str or value_str == '0':
                return 0.0
            
            # Convert to float
            raw_value = float(value_str)
            
            # Apply decimal conversion
            if decimals > 0 and decimals <= 18:  # Reasonable decimal range
                converted_value = raw_value / (10 ** decimals)
            else:
                logger.warning(f"[ERROR] Invalid decimals {decimals} for {token_symbol}, using raw value")
                converted_value = raw_value
            
            # PERBAIKAN: Sanity check untuk extremely large values
            if converted_value > 1e18:  # More than 1 billion billion
                logger.warning(f"[ERROR] Extremely large value detected for {token_symbol}: {converted_value}")
                # Kemungkinan parsing error, coba dengan decimals 18 (ETH standard)
                converted_value = raw_value / (10 ** 18)
                
                if converted_value > 1e18:  # Still too large
                    logger.warning(f"[ERROR] Value still too large, might be invalid: {converted_value}")
                    return 0.0  # Skip this transaction
            
            # PERBAIKAN: Round untuk menghindari floating point precision issues
            if converted_value < 1e-10:  # Very small value
                return 0.0
            elif converted_value < 1:
                return round(converted_value, 10)  # Keep more precision for small values
            else:
                return round(converted_value, 8)   # Normal precision for larger values
                
        except (ValueError, TypeError, OverflowError) as e:
            logger.warning(f"[ERROR] Error parsing value '{value_str}' with decimals {decimals}: {e}")
            return 0.0
    
    def get_user_transactions_moralis(self, wallet_address: str, chain: str = 'eth', 
                                    limit: int = 100) -> List[TransactionData]:
        """PERBAIKAN: Get user transactions from Moralis API dengan better error handling"""
        api_config = self.apis['moralis']
        if not api_config.api_key:
            logger.warning("Moralis API key not configured")
            return []
        
        self._wait_for_rate_limit('moralis')
        
        try:
            headers = {
                'X-API-Key': api_config.api_key,
                'Content-Type': 'application/json'
            }
            
            # PERBAIKAN: Try different endpoints based on wallet activity
            transactions = []
            
            # Try ERC20 transfers first (most common)
            url = f"{api_config.base_url}/{wallet_address}/erc20"
            params = {
                'chain': chain,
                'limit': min(limit, 100)  # Limit to prevent overload
            }
            
            logger.info(f"[SEARCH] Moralis ERC20 Request: {url} with params: {params}")
            
            response = self.session.get(url, headers=headers, params=params, timeout=10)
            
            if response.status_code == 400:
                # PERBAIKAN: Handle specific error case for high-activity wallets
                error_data = response.json() if response.content else {}
                error_msg = error_data.get('message', 'Unknown error')
                
                if 'over 2000 tokens' in error_msg:
                    logger.warning(f"[ERROR] Wallet {wallet_address} has too many tokens for Moralis free tier")
                    logger.info("[RETRY] Trying with native transactions instead...")
                    
                    # Fallback to native transactions
                    url = f"{api_config.base_url}/{wallet_address}"
                    params = {
                        'chain': chain,
                        'limit': min(limit, 50)  # Even smaller limit for native
                    }
                    
                    response = self.session.get(url, headers=headers, params=params, timeout=10)
                    
                    if response.status_code != 200:
                        logger.error(f"[ERROR] Moralis native transactions also failed: {response.status_code}")
                        return []
                else:
                    logger.error(f"[ERROR] Moralis API error: {error_msg}")
                    return []
            
            response.raise_for_status()
            
            data = response.json()
            logger.info(f"[DATA] Moralis Response: Found {len(data.get('result', []))} transactions")
            
            for tx in data.get('result', []):
                try:
                    # PERBAIKAN: Better validation
                    if not isinstance(tx, dict):
                        continue
                    
                    required_fields = ['transaction_hash', 'from_address', 'to_address']
                    if not all(field in tx for field in required_fields):
                        continue
                    
                    # Determine transaction type
                    tx_type = 'buy' if tx['to_address'].lower() == wallet_address.lower() else 'sell'
                    
                    # PERBAIKAN: Better value parsing dengan safe function
                    if 'value' in tx and 'token_decimals' in tx:
                        # ERC20 token
                        decimals = int(tx.get('token_decimals', 18))
                        token_symbol = tx.get('token_symbol', 'UNKNOWN')
                        value = self._safe_parse_value(tx['value'], decimals, token_symbol)
                        token_address = tx.get('token_address', '')
                    else:
                        # Native ETH transaction
                        value = self._safe_parse_value(tx.get('value', '0'), 18, 'ETH')
                        token_symbol = 'ETH'
                        token_address = ''

                    # PERBAIKAN: Skip transactions dengan value 0 atau invalid
                    if value <= 0:
                        logger.debug(f"[SKIP] Skipping zero-value transaction: {tx.get('transaction_hash', 'N/A')}")
                        continue
                    
                    # PERBAIKAN: Better timestamp parsing
                    timestamp_str = tx.get('block_timestamp', '')
                    if timestamp_str:
                        try:
                            if timestamp_str.endswith('Z'):
                                timestamp_str = timestamp_str[:-1] + '+00:00'
                            timestamp = datetime.fromisoformat(timestamp_str)
                        except ValueError:
                            timestamp = datetime.now()
                    else:
                        timestamp = datetime.now()
                    
                    transaction = TransactionData(
                        tx_hash=tx['transaction_hash'],
                        from_address=tx['from_address'],
                        to_address=tx['to_address'],
                        value=value,
                        token_symbol=token_symbol,
                        token_address=token_address,
                        block_number=int(tx.get('block_number', 0)),
                        timestamp=timestamp,
                        gas_used=int(tx.get('gas_used', 0)),
                        gas_price=float(tx.get('gas_price', 0)) / (10 ** 9) if tx.get('gas_price') else 0,
                        transaction_type=tx_type,
                        chain=chain
                    )
                    transactions.append(transaction)
                    
                except Exception as e:
                    logger.warning(f"[ERROR] Error parsing transaction: {e}")
                    continue
                    
            logger.info(f"[SUCCESS] Retrieved {len(transactions)} valid transactions from Moralis for {wallet_address}")
            return transactions
            
        except requests.RequestException as e:
            logger.error(f"[ERROR] Error fetching transactions from Moralis: {e}")
            if hasattr(e, 'response') and e.response is not None:
                logger.error(f"Response status: {e.response.status_code}")
                try:
                    error_detail = e.response.json()
                    logger.error(f"Response detail: {error_detail}")
                except:
                    logger.error(f"Response text: {e.response.text}")
            return []
        except Exception as e:
            logger.error(f"[ERROR] Unexpected error in Moralis API: {e}")
            return []
    
    def get_user_transactions_etherscan(self, wallet_address: str, 
                                  contract_address: Optional[str] = None) -> List[TransactionData]:
        """PERBAIKAN: Get user transactions from Etherscan API dengan parsing dan logging yang lebih baik"""
        api_config = self.apis['etherscan']
        if not api_config.api_key:
            logger.warning("Etherscan API key not configured")
            return []
        
        self._wait_for_rate_limit('etherscan')
        
        try:
            all_transactions = []  # Store all transactions from all endpoints
            
            # PERBAIKAN: Get both regular ETH transactions and ERC20 token transactions
            endpoints = [
                {'action': 'txlist', 'description': 'ETH transactions'},
                {'action': 'tokentx', 'description': 'ERC20 token transactions'}
            ]
            
            for endpoint in endpoints:
                params = {
                    'module': 'account',
                    'action': endpoint['action'],
                    'address': wallet_address,
                    'startblock': 0,
                    'endblock': 99999999,
                    'page': 1,
                    'offset': 50,  # Smaller limit per endpoint
                    'sort': 'desc',
                    'apikey': api_config.api_key
                }
                
                if contract_address and endpoint['action'] == 'tokentx':
                    params['contractaddress'] = contract_address
                
                logger.info(f"[SEARCH] Etherscan {endpoint['description']}: {api_config.base_url}")
                
                try:
                    response = self.session.get(api_config.base_url, params=params, timeout=10)
                    response.raise_for_status()
                    
                    data = response.json()
                    raw_results = data.get('result', [])
                    
                    # PERBAIKAN: Log raw API response
                    logger.info(f"[DATA] Etherscan {endpoint['description']}: Status {data.get('status')}, Raw results: {len(raw_results)}")
                    
                    endpoint_transactions = []  # Track transactions per endpoint
                    
                    if data.get('status') == '1' and isinstance(raw_results, list):
                        for tx in raw_results:
                            try:
                                if not isinstance(tx, dict):
                                    continue
                                    
                                required_fields = ['hash', 'from', 'to', 'value']
                                if not all(field in tx for field in required_fields):
                                    logger.debug(f"[SKIP] Missing required fields in transaction: {tx.get('hash', 'N/A')}")
                                    continue
                                
                                # Determine transaction type
                                tx_type = 'buy' if tx['to'].lower() == wallet_address.lower() else 'sell'
                                
                                # PERBAIKAN: Apply _safe_parse_value untuk Etherscan juga!
                                if endpoint['action'] == 'tokentx':
                                    # ERC20 token transaction
                                    decimals = int(tx.get('tokenDecimal', 18))
                                    token_symbol = tx.get('tokenSymbol', 'UNKNOWN')
                                    # PERBAIKAN: Gunakan _safe_parse_value
                                    value = self._safe_parse_value(tx['value'], decimals, token_symbol)
                                    token_address = tx.get('contractAddress', '')
                                    
                                    # PERBAIKAN: Log suspicious values for debugging
                                    if value > 1e12:
                                        logger.debug(f"[DEBUG] Large value detected for {token_symbol}: {value} (raw: {tx['value']}, decimals: {decimals})")
                                        
                                else:
                                    # ETH transaction
                                    # PERBAIKAN: Gunakan _safe_parse_value
                                    value = self._safe_parse_value(tx['value'], 18, 'ETH')
                                    token_symbol = 'ETH'
                                    token_address = ''
                                
                                # PERBAIKAN: Skip zero-value transactions dengan logging
                                if value <= 0:
                                    logger.debug(f"[SKIP] Zero-value transaction: {tx.get('hash', 'N/A')} ({token_symbol})")
                                    continue
                                
                                # Parse timestamp
                                timestamp = datetime.fromtimestamp(int(tx.get('timeStamp', 0)))
                                
                                transaction = TransactionData(
                                    tx_hash=tx['hash'],
                                    from_address=tx['from'],
                                    to_address=tx['to'],
                                    value=value,
                                    token_symbol=token_symbol,
                                    token_address=token_address,
                                    block_number=int(tx.get('blockNumber', 0)),
                                    timestamp=timestamp,
                                    gas_used=int(tx.get('gasUsed', 0)),
                                    gas_price=float(tx.get('gasPrice', 0)) / (10 ** 9) if tx.get('gasPrice') else 0,
                                    transaction_type=tx_type,
                                    chain='eth'
                                )
                                
                                endpoint_transactions.append(transaction)
                                all_transactions.append(transaction)
                                
                            except (KeyError, ValueError, TypeError) as e:
                                logger.warning(f"[ERROR] Error parsing Etherscan transaction: {e}")
                                continue
                        
                        # PERBAIKAN: Log actual results per endpoint
                        if endpoint_transactions:
                            logger.info(f"[SUCCESS] Etherscan {endpoint['description']}: Parsed {len(endpoint_transactions)} valid transactions from {len(raw_results)} raw results")
                            
                            # PERBAIKAN: Log breakdown by token type for debugging
                            if endpoint['action'] == 'tokentx':
                                token_breakdown = {}
                                for tx in endpoint_transactions:
                                    token_breakdown[tx.token_symbol] = token_breakdown.get(tx.token_symbol, 0) + 1
                                logger.debug(f"[DEBUG] Token breakdown: {token_breakdown}")
                        else:
                            logger.info(f"[SKIP] Etherscan {endpoint['description']}: No valid transactions found from {len(raw_results)} raw results")
                            
                            # PERBAIKAN: Log why no transactions were parsed
                            if len(raw_results) > 0:
                                sample_tx = raw_results[0]
                                logger.debug(f"[DEBUG] Sample transaction that was skipped: hash={sample_tx.get('hash', 'N/A')}, value={sample_tx.get('value', 'N/A')}")
                    else:
                        # PERBAIKAN: Better error logging
                        if data.get('status') == '0':
                            logger.info(f"[INFO] Etherscan {endpoint['description']}: No transactions found (Status: 0, Message: {data.get('message', 'Unknown')})")
                        else:
                            logger.warning(f"[WARNING] Etherscan {endpoint['description']}: Unexpected response format - Status: {data.get('status')}, Result type: {type(raw_results)}")
                            
                except requests.RequestException as e:
                    logger.error(f"[ERROR] Network error for Etherscan {endpoint['description']}: {e}")
                    continue
                except Exception as e:
                    logger.error(f"[ERROR] Unexpected error for Etherscan {endpoint['description']}: {e}")
                    continue
            
            # PERBAIKAN: Sort by timestamp and remove duplicates dengan detailed logging
            logger.info(f"[PROCESS] Processing {len(all_transactions)} total transactions before deduplication")
            
            all_transactions.sort(key=lambda x: x.timestamp, reverse=True)
            seen_hashes = set()
            unique_transactions = []
            duplicate_count = 0
            
            for tx in all_transactions:
                if tx.tx_hash not in seen_hashes:
                    seen_hashes.add(tx.tx_hash)
                    unique_transactions.append(tx)
                else:
                    duplicate_count += 1
                    logger.debug(f"[DEDUP] Duplicate transaction removed: {tx.tx_hash}")
            
            # PERBAIKAN: Comprehensive final logging
            logger.info(f"[SUCCESS] Retrieved {len(unique_transactions)} unique transactions from Etherscan for {wallet_address}")
            
            if duplicate_count > 0:
                logger.info(f"[DEDUP] Removed {duplicate_count} duplicate transactions")
            
            # PERBAIKAN: Log summary by transaction type and token
            if unique_transactions:
                type_breakdown = {}
                token_breakdown = {}
                value_ranges = {'small': 0, 'medium': 0, 'large': 0, 'suspicious': 0}
                
                for tx in unique_transactions:
                    # Transaction type breakdown
                    type_breakdown[tx.transaction_type] = type_breakdown.get(tx.transaction_type, 0) + 1
                    
                    # Token breakdown
                    token_breakdown[tx.token_symbol] = token_breakdown.get(tx.token_symbol, 0) + 1
                    
                    # Value range breakdown
                    if tx.value > 1e12:
                        value_ranges['suspicious'] += 1
                    elif tx.value > 1e6:
                        value_ranges['large'] += 1
                    elif tx.value > 1e3:
                        value_ranges['medium'] += 1
                    else:
                        value_ranges['small'] += 1
                
                logger.info(f"[SUMMARY] Transaction types: {type_breakdown}")
                logger.info(f"[SUMMARY] Top tokens: {dict(list(sorted(token_breakdown.items(), key=lambda x: x[1], reverse=True))[:5])}")
                logger.info(f"[SUMMARY] Value ranges: {value_ranges}")
                
                # PERBAIKAN: Warning for suspicious values
                if value_ranges['suspicious'] > 0:
                    logger.warning(f"[WARNING] Found {value_ranges['suspicious']} transactions with suspicious large values (>1e12)")
            
            return unique_transactions
            
        except requests.RequestException as e:
            logger.error(f"[ERROR] Network error fetching transactions from Etherscan: {e}")
            return []
        except Exception as e:
            logger.error(f"[ERROR] Unexpected error in Etherscan API: {e}")
            import traceback
            logger.error(f"[TRACEBACK] {traceback.format_exc()}")
            return []
    
    # Rest of the methods remain the same but with emoji replacements...
    def get_real_time_prices_binance(self, symbols: List[str]) -> Dict[str, float]:
        """Get real-time prices from Binance API (FREE & UNLIMITED)"""
        api_config = self.apis['binance']
        
        try:
            binance_symbols = []
            symbol_mapping = {}
            
            for symbol in symbols:
                if symbol.upper() == 'BTC':
                    binance_symbol = 'BTCUSDT'
                elif symbol.upper() == 'ETH':
                    binance_symbol = 'ETHUSDT'
                else:
                    binance_symbol = f"{symbol.upper()}USDT"
                
                binance_symbols.append(binance_symbol)
                symbol_mapping[binance_symbol] = symbol
            
            url = f"{api_config.base_url}/ticker/price"
            response = self.session.get(url, timeout=10)
            response.raise_for_status()
            
            all_prices = response.json()
            prices = {}
            
            for price_data in all_prices:
                symbol = price_data['symbol']
                if symbol in symbol_mapping:
                    original_symbol = symbol_mapping[symbol]
                    prices[original_symbol] = float(price_data['price'])
            
            logger.info(f"[SUCCESS] Retrieved {len(prices)} prices from Binance")
            return prices
            
        except requests.RequestException as e:
            logger.error(f"[ERROR] Error fetching prices from Binance: {e}")
            return {}
    
    def get_real_time_prices_multiple_sources(self, symbols: List[str]) -> Dict[str, float]:
        """Get prices from multiple sources for redundancy"""
        all_prices = {}
        
        # Try Binance first (fastest and most reliable)
        binance_prices = self.get_real_time_prices_binance(symbols)
        all_prices.update(binance_prices)
        
        # Fill missing symbols with CoinGecko
        missing_symbols = [s for s in symbols if s not in all_prices]
        if missing_symbols:
            coingecko_prices = self.get_prices_coingecko(missing_symbols)
            all_prices.update(coingecko_prices)
        
        return all_prices
    
    def get_prices_coingecko(self, symbols: List[str]) -> Dict[str, float]:
        """Get prices from CoinGecko API (backup)"""
        api_config = self.apis['coingecko']
        self._wait_for_rate_limit('coingecko')
        
        try:
            id_mapping = {
                'BTC': 'bitcoin',
                'ETH': 'ethereum',
                'BNB': 'binancecoin',
                'ADA': 'cardano',
                'SOL': 'solana',
                'DOT': 'polkadot',
                'AVAX': 'avalanche-2',
                'MATIC': 'matic-network',
                'UNI': 'uniswap',
                'LINK': 'chainlink'
            }
            
            coin_ids = []
            for symbol in symbols:
                if symbol.upper() in id_mapping:
                    coin_ids.append(id_mapping[symbol.upper()])
            
            if not coin_ids:
                return {}
            
            url = f"{api_config.base_url}/simple/price"
            params = {
                'ids': ','.join(coin_ids),
                'vs_currencies': 'usd'
            }
            
            if api_config.api_key:
                params['x_cg_demo_api_key'] = api_config.api_key
            
            response = self.session.get(url, params=params, timeout=10)
            response.raise_for_status()
            
            data = response.json()
            prices = {}
            
            reverse_mapping = {v: k for k, v in id_mapping.items()}
            
            for coin_id, price_data in data.items():
                if coin_id in reverse_mapping:
                    symbol = reverse_mapping[coin_id]
                    prices[symbol] = price_data['usd']
            
            logger.info(f"[SUCCESS] Retrieved {len(prices)} prices from CoinGecko")
            return prices
            
        except requests.RequestException as e:
            logger.error(f"[ERROR] Error fetching prices from CoinGecko: {e}")
            return {}
    
    def get_user_transactions_all_chains(self, wallet_address: str, 
                                       chains: List[str] = None) -> List[TransactionData]:
        """Get user transactions from all supported chains dengan debugging yang lebih baik"""
        if chains is None:
            chains = ['eth', 'bsc', 'polygon']
        
        logger.info(f"[SEARCH] Starting multi-chain transaction sync for {wallet_address} on chains: {chains}")
        
        all_transactions = []
        
        for chain in chains:
            logger.info(f"[SEARCH] Processing chain: {chain}")
            
            try:
                chain_transactions = []
                
                if chain in ['eth', 'bsc', 'polygon', 'avalanche']:
                    # Use Moralis for multi-chain support
                    chain_transactions = self.get_user_transactions_moralis(wallet_address, chain)
                    logger.info(f"[DATA] Moralis returned {len(chain_transactions)} transactions for {chain}")
                
                # For Ethereum, also try Etherscan as fallback
                if chain == 'eth' and len(chain_transactions) == 0:
                    logger.info(f"[RETRY] Trying Etherscan fallback for ETH")
                    etherscan_transactions = self.get_user_transactions_etherscan(wallet_address)
                    chain_transactions.extend(etherscan_transactions)
                    logger.info(f"[DATA] Etherscan returned {len(etherscan_transactions)} additional transactions")
                
                all_transactions.extend(chain_transactions)
                logger.info(f"[SUCCESS] Chain {chain} complete: {len(chain_transactions)} transactions")
                
            except Exception as e:
                logger.error(f"[ERROR] Error getting transactions for {chain}: {e}")
                continue
        
        # Sort by timestamp (newest first)
        all_transactions.sort(key=lambda x: x.timestamp, reverse=True)
        
        logger.info(f"[COMPLETE] Total transactions retrieved: {len(all_transactions)} from {len(chains)} chains")
        return all_transactions
    
    def save_transactions_to_csv(self, transactions: List[TransactionData], 
                                filename: str = 'user_transactions.csv'):
        """Save transactions to CSV file"""
        if not transactions:
            logger.warning("No transactions to save")
            return
        
        if not PANDAS_AVAILABLE:
            logger.error("Pandas not available - cannot save to CSV")
            return
        
        data = []
        for tx in transactions:
            data.append({
                'tx_hash': tx.tx_hash,
                'from_address': tx.from_address,
                'to_address': tx.to_address,
                'value': tx.value,
                'token_symbol': tx.token_symbol,
                'token_address': tx.token_address,
                'block_number': tx.block_number,
                'timestamp': tx.timestamp.isoformat(),
                'gas_used': tx.gas_used,
                'gas_price': tx.gas_price,
                'transaction_type': tx.transaction_type,
                'chain': tx.chain,
                'project_id': tx.project_id
            })
        
        df = pd.DataFrame(data)
        df.to_csv(filename, index=False)
        logger.info(f"[SUCCESS] Saved {len(transactions)} transactions to {filename}")

# Example usage with better test addresses
if __name__ == "__main__":
    collector = MultiAPICollector()
    
    # PERBAIKAN: Use different test addresses untuk different scenarios
    test_addresses = [
        "0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045",  # Vitalik - famous but might have too many tokens
        "0x742E0000f7E6b9fFc0C0C9E84C7B4F8e3c7DC1",    # Smaller active wallet
        "0x3f5CE5FBFe3E9af3971dD833D26bA9b5C936f0bE"     # Binance hot wallet
    ]
    
    for test_address in test_addresses:
        print(f"\n[SEARCH] Testing address: {test_address}")
        
        # Get transactions
        transactions = collector.get_user_transactions_all_chains(
            test_address, 
            chains=['eth']  # Start with ETH only for testing
        )
        
        if transactions:
            print(f"[SUCCESS] Found {len(transactions)} transactions")
            for tx in transactions[:3]:  # Show first 3
                print(f"  {tx.timestamp}: {tx.transaction_type} {tx.value} {tx.token_symbol}")
            break
        else:
            print(f"[ERROR] No transactions found for {test_address}")
    
    # Get real-time prices
    prices = collector.get_real_time_prices_multiple_sources(['BTC', 'ETH', 'BNB'])
    print(f"\n[DATA] Current prices: {prices}")