# ⚡ NEW: multichain_helpers.py - Helper functions untuk multi-chain analytics

import asyncio
import aiohttp
import logging
from typing import Dict, List, Optional, Any, Tuple
from datetime import datetime, timedelta
import statistics
from config import CHAIN_CONFIGS, NATIVE_TOKEN_MAPPING, MULTI_CHAIN_CONFIG, CROSS_CHAIN_WEIGHTS

logger = logging.getLogger(__name__)

class MultiChainAnalyticsHelper:
    """⚡ Helper class untuk multi-chain analytics operations"""
    
    def __init__(self):
        self.chain_configs = CHAIN_CONFIGS
        self.native_mapping = NATIVE_TOKEN_MAPPING
        self.config = MULTI_CHAIN_CONFIG
        
    async def fetch_parallel_chain_data(self, session: aiohttp.ClientSession, wallet_address: str, 
                                      chains: List[str], selected_chain: str = None) -> Dict[str, Any]:
        """⚡ Fetch data dari multiple chains secara parallel"""
        
        # Determine which chains to process
        target_chains = [selected_chain] if selected_chain else chains
        
        logger.info(f"⚡ MULTI-CHAIN: Starting parallel fetch untuk {len(target_chains)} chains")
        
        # Create tasks untuk parallel execution
        tasks = []
        for chain in target_chains:
            if chain in self.chain_configs:
                task = asyncio.create_task(
                    self._fetch_single_chain_data(session, wallet_address, chain),
                    name=f"chain_{chain}"
                )
                tasks.append((chain, task))
        
        # Execute parallel dengan timeout
        results = {}
        errors = []
        
        try:
            # Wait untuk semua tasks dengan timeout
            completed_tasks = await asyncio.wait_for(
                asyncio.gather(*[task for _, task in tasks], return_exceptions=True),
                timeout=self.config["chain_request_timeout"] * len(tasks)
            )
            
            # Process results
            for i, (chain, task) in enumerate(tasks):
                try:
                    if i < len(completed_tasks):
                        result = completed_tasks[i]
                        if isinstance(result, Exception):
                            logger.error(f"⚡ ERROR: Chain {chain} failed: {result}")
                            errors.append({"chain": chain, "error": str(result)})
                        else:
                            results[chain] = result
                            logger.info(f"⚡ SUCCESS: Chain {chain} completed")
                except Exception as e:
                    logger.error(f"⚡ ERROR: Processing result untuk {chain}: {e}")
                    errors.append({"chain": chain, "error": str(e)})
                    
        except asyncio.TimeoutError:
            logger.error(f"⚡ TIMEOUT: Parallel fetch timeout after {self.config['chain_request_timeout']} seconds")
            errors.append({"chain": "all", "error": "Parallel fetch timeout"})
        
        logger.info(f"⚡ MULTI-CHAIN: Completed with {len(results)} successful chains, {len(errors)} errors")
        
        return {
            "results": results,
            "errors": errors,
            "chains_processed": list(results.keys()),
            "chains_failed": [error["chain"] for error in errors]
        }
    
    async def _fetch_single_chain_data(self, session: aiohttp.ClientSession, 
                                     wallet_address: str, chain: str) -> Dict[str, Any]:
        """⚡ Fetch data dari single chain dengan error handling"""
        
        start_time = datetime.now()
        chain_config = self.chain_configs.get(chain, {})
        max_transactions = chain_config.get("max_transactions", 200)
        
        try:
            # Construct Moralis API URL untuk chain ini
            base_url = "https://deep-index.moralis.io/api/v2.2"
            headers = {
                'X-API-Key': os.environ.get('MORALIS_API_KEY', ''),
                'Content-Type': 'application/json'
            }
            
            # Fetch transactions untuk chain ini
            transactions_url = f"{base_url}/{wallet_address}/erc20/transfers"
            params = {
                'chain': chain,
                'limit': max_transactions,
                'order': 'DESC'
            }
            
            transactions = []
            token_stats = {}
            
            async with session.get(transactions_url, headers=headers, params=params, 
                                 timeout=aiohttp.ClientTimeout(total=15)) as response:
                if response.status == 200:
                    data = await response.json()
                    
                    # Process transactions
                    raw_transactions = data.get('result', []) if isinstance(data, dict) else data
                    
                    for tx in raw_transactions[:max_transactions]:
                        try:
                            processed_tx = self._process_transaction(tx, chain)
                            transactions.append(processed_tx)
                            
                            # Update token stats
                            symbol = processed_tx.get('token_symbol', 'UNKNOWN')
                            if symbol not in token_stats:
                                token_stats[symbol] = {
                                    'symbol': symbol,
                                    'trade_count': 0,
                                    'volume': 0.0,
                                    'volume_usd': 0.0,
                                    'chain': chain
                                }
                            
                            token_stats[symbol]['trade_count'] += 1
                            token_stats[symbol]['volume'] += processed_tx.get('value', 0)
                            
                        except Exception as e:
                            logger.warning(f"⚡ WARNING: Error processing transaction untuk {chain}: {e}")
                            continue
            
            # Calculate processing time
            processing_time = (datetime.now() - start_time).total_seconds() * 1000
            
            # Return chain-specific data
            return {
                'chain': chain,
                'transactions': transactions,
                'token_stats': token_stats,
                'transaction_count': len(transactions),
                'unique_tokens': len(token_stats),
                'processing_time_ms': processing_time,
                'native_token': chain_config.get('symbol', 'UNKNOWN'),
                'success': True
            }
            
        except Exception as e:
            processing_time = (datetime.now() - start_time).total_seconds() * 1000
            logger.error(f"⚡ ERROR: Failed to fetch {chain} data: {e}")
            
            return {
                'chain': chain,
                'transactions': [],
                'token_stats': {},
                'transaction_count': 0,
                'unique_tokens': 0,
                'processing_time_ms': processing_time,
                'native_token': chain_config.get('symbol', 'UNKNOWN'),
                'success': False,
                'error': str(e)
            }
    
    def _process_transaction(self, tx: Dict[str, Any], chain: str) -> Dict[str, Any]:
        """⚡ Process single transaction dengan chain context"""
        
        try:
            return {
                'hash': tx.get('transaction_hash', ''),
                'block_number': int(tx.get('block_number', 0)),
                'timestamp': tx.get('block_timestamp', ''),
                'from_address': tx.get('from_address', ''),
                'to_address': tx.get('to_address', ''),
                'value': float(tx.get('value', 0)) / (10 ** int(tx.get('token_decimals', 18))),
                'token_symbol': tx.get('token_symbol', self.chain_configs.get(chain, {}).get('symbol', 'UNKNOWN')),
                'token_address': tx.get('address', ''),
                'chain': chain,
                'transaction_type': 'token' if tx.get('address') else 'native',
                'gas_used': int(tx.get('gas_used', 0)) if tx.get('gas_used') else None,
                'gas_price': tx.get('gas_price', '0')
            }
        except Exception as e:
            logger.warning(f"⚡ WARNING: Error processing transaction: {e}")
            return {}
    
    def aggregate_multi_chain_data(self, chain_results: Dict[str, Any]) -> Dict[str, Any]:
        """⚡ Aggregate data dari multiple chains"""
        
        logger.info(f"⚡ AGGREGATING: Processing {len(chain_results)} chain results")
        
        # Initialize aggregated data
        aggregated = {
            'total_transactions': 0,
            'unique_tokens_traded': 0,
            'total_volume_usd': 0.0,
            'most_traded_tokens': [],
            'transaction_frequency': {},
            'chains_activity': {},
            'chain_breakdown': {},
            'processing_stats': {
                'total_processing_time_ms': 0,
                'successful_chains': 0,
                'failed_chains': 0
            }
        }
        
        # Aggregate token stats across chains
        global_token_stats = {}
        
        for chain, data in chain_results.items():
            if not data.get('success', False):
                aggregated['processing_stats']['failed_chains'] += 1
                continue
                
            aggregated['processing_stats']['successful_chains'] += 1
            aggregated['processing_stats']['total_processing_time_ms'] += data.get('processing_time_ms', 0)
            
            # Chain-level stats
            chain_transaction_count = data.get('transaction_count', 0)
            aggregated['total_transactions'] += chain_transaction_count
            aggregated['chains_activity'][chain] = chain_transaction_count
            
            # Store chain breakdown
            aggregated['chain_breakdown'][chain] = {
                'transactions': chain_transaction_count,
                'unique_tokens': data.get('unique_tokens', 0),
                'native_token': data.get('native_token', 'UNKNOWN'),
                'processing_time_ms': data.get('processing_time_ms', 0)
            }
            
            # Aggregate token stats
            for symbol, stats in data.get('token_stats', {}).items():
                if symbol not in global_token_stats:
                    global_token_stats[symbol] = {
                        'symbol': symbol,
                        'trade_count': 0,
                        'volume': 0.0,
                        'volume_usd': 0.0,
                        'chains': []
                    }
                
                global_token_stats[symbol]['trade_count'] += stats.get('trade_count', 0)
                global_token_stats[symbol]['volume'] += stats.get('volume', 0.0)
                global_token_stats[symbol]['volume_usd'] += stats.get('volume_usd', 0.0)
                
                if chain not in global_token_stats[symbol]['chains']:
                    global_token_stats[symbol]['chains'].append(chain)
            
            # Aggregate transaction frequency
            for tx in data.get('transactions', []):
                try:
                    if tx.get('timestamp'):
                        date_str = tx['timestamp'][:10]  # YYYY-MM-DD
                        aggregated['transaction_frequency'][date_str] = \
                            aggregated['transaction_frequency'].get(date_str, 0) + 1
                except:
                    continue
        
        # Calculate final metrics
        aggregated['unique_tokens_traded'] = len(global_token_stats)
        aggregated['total_volume_usd'] = sum(token['volume_usd'] for token in global_token_stats.values())
        
        # Sort most traded tokens
        sorted_tokens = sorted(global_token_stats.values(), 
                             key=lambda x: x['trade_count'], reverse=True)
        aggregated['most_traded_tokens'] = sorted_tokens[:10]
        
        logger.info(f"⚡ AGGREGATED: {aggregated['total_transactions']} total txs, " +
                   f"{aggregated['unique_tokens_traded']} tokens, " + 
                   f"${aggregated['total_volume_usd']:.2f} volume")
        
        return aggregated
    
    def calculate_diversification_score(self, chains_activity: Dict[str, int]) -> float:
        """⚡ Calculate portfolio diversification score across chains"""
        
        if not chains_activity:
            return 0.0
            
        total_activity = sum(chains_activity.values())
        if total_activity == 0:
            return 0.0
        
        # Calculate distribution entropy
        distributions = [count / total_activity for count in chains_activity.values()]
        entropy = -sum(p * np.log2(p) for p in distributions if p > 0)
        
        # Normalize to 0-100 scale
        max_entropy = np.log2(len(chains_activity))
        normalized_score = (entropy / max_entropy) * 100 if max_entropy > 0 else 0
        
        return min(100.0, max(0.0, normalized_score))
    
    def calculate_chain_dominance(self, chains_activity: Dict[str, int]) -> Dict[str, float]:
        """⚡ Calculate dominance percentage untuk each chain"""
        
        total_activity = sum(chains_activity.values())
        if total_activity == 0:
            return {}
        
        return {
            chain: (count / total_activity) * 100 
            for chain, count in chains_activity.items()
        }
    
    def get_cross_chain_insights(self, aggregated_data: Dict[str, Any], 
                               selected_chain: str = None) -> Dict[str, Any]:
        """⚡ Generate cross-chain insights dan recommendations"""
        
        insights = {
            'preferred_chains': [],
            'diversification_recommendation': '',
            'risk_warnings': [],
            'optimization_suggestions': [],
            'cross_chain_score': 0.0
        }
        
        chains_activity = aggregated_data.get('chains_activity', {})
        if not chains_activity:
            return insights
        
        # Calculate chain dominance
        dominance = self.calculate_chain_dominance(chains_activity)
        
        # Find preferred chains (top 3)
        sorted_chains = sorted(dominance.items(), key=lambda x: x[1], reverse=True)
        insights['preferred_chains'] = [chain for chain, _ in sorted_chains[:3]]
        
        # Diversification analysis
        diversification_score = self.calculate_diversification_score(chains_activity)
        insights['cross_chain_score'] = diversification_score
        
        if diversification_score > 70:
            insights['diversification_recommendation'] = "Excellent multi-chain diversification"
        elif diversification_score > 40:
            insights['diversification_recommendation'] = "Good diversification, consider expanding to more chains"
        else:
            insights['diversification_recommendation'] = "Limited diversification, recommend multi-chain strategy"
        
        # Risk warnings
        max_dominance = max(dominance.values()) if dominance else 0
        if max_dominance > 80:
            dominant_chain = max(dominance.items(), key=lambda x: x[1])[0]
            insights['risk_warnings'].append(f"High concentration risk: {max_dominance:.1f}% pada {dominant_chain}")
        
        # Optimization suggestions
        if len(chains_activity) < 3:
            insights['optimization_suggestions'].append("Consider expanding ke lebih banyak chains untuk better yields")
        
        if diversification_score < 50:
            insights['optimization_suggestions'].append("Redistribute aktivitas untuk better diversification")
        
        return insights

# ⚡ Initialize global helper instance
multi_chain_helper = MultiChainAnalyticsHelper()