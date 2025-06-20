import os
import requests
import time
import logging
from typing import Dict, List, Optional, Union
from datetime import datetime, timedelta
import pandas as pd
from dataclasses import dataclass
from concurrent.futures import ThreadPoolExecutor, as_completed
import json

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

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
            'source': 'api_sync',  # Always api_sync for engine-collected transactions
            'is_verified': True,   # API-synced transactions are verified
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
        """Load API configurations from environment variables"""
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
    
    def get_user_transactions_moralis(self, wallet_address: str, chain: str = 'eth', 
                                    limit: int = 100) -> List[TransactionData]:
        """Get user transactions from Moralis API"""
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
            
            # Get ERC20 token transfers
            url = f"{api_config.base_url}/{wallet_address}/erc20"
            params = {
                'chain': chain,
                'limit': limit
            }
            
            response = self.session.get(url, headers=headers, params=params, timeout=10)
            response.raise_for_status()
            
            data = response.json()
            transactions = []
            
            for tx in data.get('result', []):
                try:
                    # Determine transaction type
                    tx_type = 'buy' if tx['to_address'].lower() == wallet_address.lower() else 'sell'
                    
                    # Convert value from wei to token units
                    decimals = int(tx.get('token_decimals', 18))
                    value = float(tx['value']) / (10 ** decimals)
                    
                    transaction = TransactionData(
                        tx_hash=tx['transaction_hash'],
                        from_address=tx['from_address'],
                        to_address=tx['to_address'],
                        value=value,
                        token_symbol=tx.get('token_symbol', 'UNKNOWN'),
                        token_address=tx['token_address'],
                        block_number=int(tx['block_number']),
                        timestamp=datetime.fromisoformat(tx['block_timestamp'].replace('Z', '+00:00')),
                        gas_used=0,  # Not available in this endpoint
                        gas_price=0,  # Not available in this endpoint
                        transaction_type=tx_type,
                        chain=chain
                    )
                    transactions.append(transaction)
                    
                except (KeyError, ValueError) as e:
                    logger.warning(f"Error parsing transaction: {e}")
                    continue
                    
            logger.info(f"Retrieved {len(transactions)} transactions from Moralis for {wallet_address}")
            return transactions
            
        except requests.RequestException as e:
            logger.error(f"Error fetching transactions from Moralis: {e}")
            return []
    
    def get_user_transactions_etherscan(self, wallet_address: str, 
                                      contract_address: Optional[str] = None) -> List[TransactionData]:
        """Get user transactions from Etherscan API"""
        api_config = self.apis['etherscan']
        if not api_config.api_key:
            logger.warning("Etherscan API key not configured")
            return []
        
        self._wait_for_rate_limit('etherscan')
        
        try:
            params = {
                'module': 'account',
                'action': 'tokentx' if contract_address else 'txlist',
                'address': wallet_address,
                'startblock': 0,
                'endblock': 99999999,
                'page': 1,
                'offset': 100,
                'sort': 'desc',
                'apikey': api_config.api_key
            }
            
            if contract_address:
                params['contractaddress'] = contract_address
            
            response = self.session.get(api_config.base_url, params=params, timeout=10)
            response.raise_for_status()
            
            data = response.json()
            transactions = []
            
            if data['status'] == '1':
                for tx in data['result']:
                    try:
                        # Determine transaction type
                        tx_type = 'buy' if tx['to'].lower() == wallet_address.lower() else 'sell'
                        
                        # Convert value
                        if contract_address:
                            decimals = int(tx.get('tokenDecimal', 18))
                            value = float(tx['value']) / (10 ** decimals)
                            token_symbol = tx.get('tokenSymbol', 'UNKNOWN')
                            token_address = tx.get('contractAddress', '')
                        else:
                            value = float(tx['value']) / (10 ** 18)  # ETH
                            token_symbol = 'ETH'
                            token_address = ''
                        
                        transaction = TransactionData(
                            tx_hash=tx['hash'],
                            from_address=tx['from'],
                            to_address=tx['to'],
                            value=value,
                            token_symbol=token_symbol,
                            token_address=token_address,
                            block_number=int(tx['blockNumber']),
                            timestamp=datetime.fromtimestamp(int(tx['timeStamp'])),
                            gas_used=int(tx.get('gasUsed', 0)),
                            gas_price=float(tx.get('gasPrice', 0)) / (10 ** 9),  # Convert to Gwei
                            transaction_type=tx_type,
                            chain='eth'
                        )
                        transactions.append(transaction)
                        
                    except (KeyError, ValueError) as e:
                        logger.warning(f"Error parsing Etherscan transaction: {e}")
                        continue
            
            logger.info(f"Retrieved {len(transactions)} transactions from Etherscan for {wallet_address}")
            return transactions
            
        except requests.RequestException as e:
            logger.error(f"Error fetching transactions from Etherscan: {e}")
            return []
    
    def get_real_time_prices_binance(self, symbols: List[str]) -> Dict[str, float]:
        """Get real-time prices from Binance API (FREE & UNLIMITED)"""
        api_config = self.apis['binance']
        
        try:
            # Binance expects symbols in format like 'BTCUSDT'
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
            
            # Get ticker prices for all symbols
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
            
            logger.info(f"Retrieved {len(prices)} prices from Binance")
            return prices
            
        except requests.RequestException as e:
            logger.error(f"Error fetching prices from Binance: {e}")
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
            # Convert symbols to CoinGecko IDs (simplified mapping)
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
            
            # Reverse mapping
            reverse_mapping = {v: k for k, v in id_mapping.items()}
            
            for coin_id, price_data in data.items():
                if coin_id in reverse_mapping:
                    symbol = reverse_mapping[coin_id]
                    prices[symbol] = price_data['usd']
            
            logger.info(f"Retrieved {len(prices)} prices from CoinGecko")
            return prices
            
        except requests.RequestException as e:
            logger.error(f"Error fetching prices from CoinGecko: {e}")
            return {}
    
    def get_user_transactions_all_chains(self, wallet_address: str, 
                                       chains: List[str] = None) -> List[TransactionData]:
        """Get user transactions from all supported chains"""
        if chains is None:
            chains = ['eth', 'bsc', 'polygon']
        
        all_transactions = []
        
        # Use ThreadPoolExecutor for parallel requests
        with ThreadPoolExecutor(max_workers=3) as executor:
            future_to_chain = {}
            
            for chain in chains:
                if chain in ['eth', 'bsc', 'polygon', 'avalanche']:
                    # Use Moralis for multi-chain support
                    future = executor.submit(self.get_user_transactions_moralis, 
                                           wallet_address, chain)
                    future_to_chain[future] = chain
                elif chain == 'eth':
                    # Also try Etherscan for Ethereum
                    future = executor.submit(self.get_user_transactions_etherscan, 
                                           wallet_address)
                    future_to_chain[future] = chain
            
            for future in as_completed(future_to_chain):
                chain = future_to_chain[future]
                try:
                    transactions = future.result()
                    all_transactions.extend(transactions)
                    logger.info(f"Retrieved {len(transactions)} transactions from {chain}")
                except Exception as e:
                    logger.error(f"Error getting transactions for {chain}: {e}")
        
        # Sort by timestamp (newest first)
        all_transactions.sort(key=lambda x: x.timestamp, reverse=True)
        
        logger.info(f"Total transactions retrieved: {len(all_transactions)}")
        return all_transactions
    
    def save_transactions_to_csv(self, transactions: List[TransactionData], 
                                filename: str = 'user_transactions.csv'):
        """Save transactions to CSV file"""
        if not transactions:
            logger.warning("No transactions to save")
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
        logger.info(f"Saved {len(transactions)} transactions to {filename}")

# Example usage
if __name__ == "__main__":
    collector = MultiAPICollector()
    
    # Test wallet address (Vitalik's address for example)
    test_address = "0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045"
    
    # Get transactions from multiple chains
    transactions = collector.get_user_transactions_all_chains(
        test_address, 
        chains=['eth', 'bsc', 'polygon']
    )
    
    # Get real-time prices
    prices = collector.get_real_time_prices_multiple_sources(['BTC', 'ETH', 'BNB'])
    print("Current prices:", prices)
    
    # Save transactions
    if transactions:
        collector.save_transactions_to_csv(transactions)
        print(f"Found {len(transactions)} transactions")
        for tx in transactions[:5]:  # Show first 5
            print(f"{tx.timestamp}: {tx.transaction_type} {tx.value} {tx.token_symbol} on {tx.chain}")