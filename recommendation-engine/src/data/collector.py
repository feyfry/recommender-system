import os
import json
import pandas as pd
import requests
from datetime import datetime, timedelta
import logging
import time
from typing import Dict, List, Optional, Any, Union

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import (
    COINGECKO_API_URL, 
    COINGECKO_API_KEY, 
    RAW_DIR, 
    TOP_COINS_LIMIT, 
    TOP_COINS_DETAIL,
    CATEGORIES
)

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class CoinGeckoCollector:
    """
    Class untuk mengumpulkan data cryptocurrency dari CoinGecko API
    """
    
    def __init__(self, rate_limit: float = 2.0):
        self.api_url = COINGECKO_API_URL
        self.api_key = COINGECKO_API_KEY
        self.rate_limit = rate_limit
        
        # Buat direktori jika belum ada
        os.makedirs(RAW_DIR, exist_ok=True)
        
        # Log konfigurasi
        logger.info(f"Initialized CoinGeckoCollector with rate limit {rate_limit}s")
        if self.api_key:
            logger.info("Using CoinGecko API key")
        
    def ping_api(self) -> bool:
        try:
            url = f"{self.api_url}/ping"
            response = requests.get(url)
            return response.status_code == 200
        except Exception as e:
            logger.error(f"Error pinging CoinGecko API: {e}")
            return False
    
    def make_request(self, endpoint: str, params: Dict = None) -> Optional[Dict]:
        url = f"{self.api_url}/{endpoint}"
        headers = {}
        
        # Add API key to headers if available
        if self.api_key:
            headers['x-cg-demo-api-key'] = self.api_key
        
        try:
            # Add delay for rate limiting
            time.sleep(self.rate_limit)
            
            # Make request
            response = requests.get(url, params=params, headers=headers)
            
            # Check for rate limiting (429)
            if response.status_code == 429:
                logger.warning("Rate limit hit, waiting 60 seconds")
                time.sleep(60)
                return self.make_request(endpoint, params)  # Retry
            
            # Check for other errors
            if response.status_code != 200:
                logger.error(f"API Error {response.status_code}: {response.text}")
                return None
            
            # Return parsed JSON
            return response.json()
            
        except Exception as e:
            logger.error(f"Error making request to {endpoint}: {e}")
            return None
    
    def fetch_top_coins(self, limit: int = TOP_COINS_LIMIT) -> Optional[List[Dict]]:
        logger.info(f"Fetching top {limit} coins")
        
        # Calculate number of pages (max 250 per page)
        max_per_page = 250
        num_pages = (limit + max_per_page - 1) // max_per_page
        
        all_coins = []
        for page in range(1, num_pages + 1):
            per_page = min(max_per_page, limit - len(all_coins))
            
            logger.info(f"Fetching page {page} with {per_page} coins")
            
            params = {
                'vs_currency': 'usd',
                'order': 'market_cap_desc',
                'per_page': per_page,
                'page': page,
                'price_change_percentage': '1h,24h,7d,30d'
            }
            
            data = self.make_request('coins/markets', params)
            
            if data:
                # Add query_category field to track source
                for item in data:
                    item['query_category'] = 'top'
                    
                all_coins.extend(data)
                
                # Save data to file
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                filename = os.path.join(RAW_DIR, f"coins_markets_{timestamp}_page{page}.json")
                
                with open(filename, 'w', encoding='utf-8') as f:
                    json.dump(data, f, ensure_ascii=False, indent=2)
                
                logger.info(f"Saved {len(data)} coins to {filename}")
                
                # Check if we have enough coins
                if len(all_coins) >= limit:
                    break
            else:
                logger.error("Failed to fetch coins")
                break
        
        # Save combined data
        if all_coins:
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filename = os.path.join(RAW_DIR, f"coins_markets_{timestamp}_all.json")
            
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(all_coins, f, ensure_ascii=False, indent=2)
            
            logger.info(f"Saved {len(all_coins)} total coins to {filename}")
        
        return all_coins
    
    def fetch_category_coins(self, category: str) -> Optional[List[Dict]]:
        logger.info(f"Fetching coins for category: {category}")
        
        params = {
            'vs_currency': 'usd',
            'order': 'market_cap_desc',
            'per_page': 250,  # Max per page
            'page': 1,
            'price_change_percentage': '1h,24h,7d,30d',
            'category': category
        }
        
        data = self.make_request('coins/markets', params)
        
        if data:
            # Add query_category field to track source
            for item in data:
                item['query_category'] = category
                
            # Save to file
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filename = os.path.join(RAW_DIR, f"coins_markets_{category}_{timestamp}.json")
            
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            
            logger.info(f"Saved {len(data)} {category} coins to {filename}")
            
            return data
        else:
            logger.error(f"Failed to fetch coins for category: {category}")
            return None
    
    def fetch_coin_details(self, coin_id: str, index: int = 0, total: int = 0) -> Optional[Dict]:
        progress = f"({index}/{total})" if total > 0 else ""
        logger.info(f"Fetching details for {coin_id} {progress}")
        
        # Pastikan untuk mendapatkan market_data, community_data dan developer_data
        params = {
            'localization': 'false',
            'tickers': 'false',
            'market_data': 'true',
            'community_data': 'true',
            'developer_data': 'true',
        }
        
        data = self.make_request(f'coins/{coin_id}', params)
        
        if data:
            # Perbaikan: Pastikan semua data sosial tersedia atau diberi nilai default
            if 'community_data' not in data or data['community_data'] is None:
                data['community_data'] = {}
            
            # Pastikan semua field sosial tersedia dengan nilai default
            social_fields = [
                'twitter_followers', 'reddit_subscribers', 'telegram_channel_user_count',
                'facebook_likes', 'discord_members'
            ]
            
            for field in social_fields:
                if field not in data['community_data']:
                    data['community_data'][field] = 0
                
            # Developer data
            if 'developer_data' not in data or data['developer_data'] is None:
                data['developer_data'] = {}
                
            dev_fields = ['stars', 'forks', 'subscribers', 'total_issues', 'pull_requests_merged']
            for field in dev_fields:
                if field not in data['developer_data']:
                    data['developer_data'][field] = 0
                    
            # Save to file
            filename = os.path.join(RAW_DIR, f"coin_details_{coin_id}.json")
            
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            
            logger.info(f"Saved details for {coin_id}")
            
            return data
        else:
            logger.error(f"Failed to fetch details for {coin_id}")
            return None
    
    def fetch_coin_categories(self) -> Optional[List[Dict]]:
        logger.info("Fetching coin categories")
        
        data = self.make_request('coins/categories')
        
        if data:
            # Save to file
            filename = os.path.join(RAW_DIR, "coins_categories.json")
            
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            
            logger.info(f"Saved {len(data)} categories")
            
            return data
        else:
            logger.error("Failed to fetch categories")
            return None
    
    def fetch_trending_coins(self) -> Optional[Dict]:
        logger.info("Fetching trending coins")
        
        data = self.make_request('search/trending')
        
        if data:
            # Save to file
            filename = os.path.join(RAW_DIR, "trending_coins.json")
            
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            
            logger.info("Saved trending coins data")
            
            return data
        else:
            logger.error("Failed to fetch trending coins")
            return None
    
    def collect_all_data(self, limit: int = TOP_COINS_LIMIT, detail_limit: int = TOP_COINS_DETAIL, 
                         include_categories: bool = False) -> bool:
        try:
            start_time = time.time()
            
            # 1. Fetch top coins
            top_coins = self.fetch_top_coins(limit)
            
            if not top_coins:
                logger.error("Failed to fetch top coins")
                return False
            
            # Collect all coins data
            all_coins = top_coins.copy()
            
            # 2. Fetch coins by category (only if explicitly requested)
            if include_categories:
                logger.info("Including category-specific coins as requested")
                for category in CATEGORIES:
                    logger.info(f"Processing category: {category}")
                    category_coins = self.fetch_category_coins(category)
                    
                    if category_coins:
                        # Add to all coins
                        all_coins.extend(category_coins)
                        
                    # Respect rate limits
                    time.sleep(self.rate_limit)
            else:
                logger.info("Skipping category-specific coin collection")
            
            # Remove duplicates based on id
            unique_ids = set()
            unique_coins = []
            
            for coin in all_coins:
                if coin['id'] not in unique_ids:
                    unique_ids.add(coin['id'])
                    unique_coins.append(coin)
            
            logger.info(f"Collected {len(unique_coins)} unique coins" + 
                       (" across all categories" if include_categories else " from top coins"))
            
            # Save combined unique coins to single file
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            combined_filename = os.path.join(RAW_DIR, f"combined_coins_{timestamp}.json")
            
            with open(combined_filename, 'w', encoding='utf-8') as f:
                json.dump(unique_coins, f, ensure_ascii=False, indent=2)
            
            logger.info(f"Saved {len(unique_coins)} unique coins to {combined_filename}")
            
            # Also save as CSV for easier analysis
            coins_df = pd.DataFrame(unique_coins)
            csv_filename = os.path.join(RAW_DIR, f"combined_coins_{timestamp}.csv")
            coins_df.to_csv(csv_filename, index=False)
            logger.info(f"Saved combined coins as CSV to {csv_filename}")
            
            # 3. Fetch detailed info for top coins
            if detail_limit > 0:
                # Get top N coins
                detail_ids = [coin['id'] for coin in top_coins[:detail_limit]]
                
                logger.info(f"Fetching details for {len(detail_ids)} coins")
                
                success_count = 0
                for idx, coin_id in enumerate(detail_ids, 1):
                    if self.fetch_coin_details(coin_id, idx, len(detail_ids)):
                        success_count += 1
                    
                    # Respect rate limits
                    time.sleep(self.rate_limit)
                
                logger.info(f"Successfully fetched details for {success_count}/{len(detail_ids)} coins")
            
            # 4. Fetch categories
            categories = self.fetch_coin_categories()
            
            if not categories:
                logger.warning("Failed to fetch categories")
            
            # 5. Fetch trending coins
            trending = self.fetch_trending_coins()
            
            if not trending:
                logger.warning("Failed to fetch trending coins")
            
            # Calculate duration
            duration = time.time() - start_time
            logger.info(f"Data collection completed in {duration:.2f} seconds")
            
            return True
            
        except Exception as e:
            logger.error(f"Error collecting all data: {e}")
            import traceback
            logger.error(traceback.format_exc())
            return False

def fetch_real_market_data(coin_id, days=30, use_cache=True):
    # Check cache if use_cache=True
    cache_dir = "data/cache"
    os.makedirs(cache_dir, exist_ok=True)
    cache_file = f"{cache_dir}/{coin_id}_{days}days.json"
    
    # Use cache if exists and is fresh (less than 1 hour old)
    if use_cache and os.path.exists(cache_file):
        file_modified_time = os.path.getmtime(cache_file)
        current_time = datetime.now().timestamp()
        
        # Cache is fresh if less than 1 hour old
        if current_time - file_modified_time < 3600:
            try:
                logger.info(f"Using cached data for {coin_id}")
                with open(cache_file, 'r') as f:
                    cached_data = json.load(f)
                    df = pd.DataFrame(cached_data)
                    
                    # Convert timestamp column to datetime if exists
                    if 'timestamp' in df.columns:
                        df['timestamp'] = pd.to_datetime(df['timestamp'])
                        df = df.set_index('timestamp')
                    
                    return df
            except Exception as e:
                logger.warning(f"Error loading cache: {str(e)}")
                # Continue to fetch new data if error
    
    try:
        # CoinGecko API URL for historical data
        url = f"https://api.coingecko.com/api/v3/coins/{coin_id}/market_chart"
        
        # Parameters for daily data
        params = {
            'vs_currency': 'usd',
            'days': days,
            'interval': 'daily'
        }
        
        # Add API key if available
        headers = {}
        if COINGECKO_API_KEY:
            headers['x-cg-demo-api-key'] = COINGECKO_API_KEY
        
        # Make API request with rate limiting precaution
        logger.info(f"Requesting data from CoinGecko API for {coin_id}")
        response = requests.get(url, params=params, headers=headers)
        
        # Check for rate limiting
        if response.status_code == 429:
            logger.warning("Rate limit hit, waiting 60 seconds...")
            time.sleep(60)
            response = requests.get(url, params=params, headers=headers)
        
        if response.status_code != 200:
            logger.error(f"API Error: {response.status_code} - {response.text}")
            return pd.DataFrame()
            
        data = response.json()
        
        # Extract price, volume, and market_cap
        prices = data.get('prices', [])
        volumes = data.get('total_volumes', [])
        
        # Create DataFrame
        df_data = []
        for i, (timestamp_price, price) in enumerate(prices):
            date = datetime.fromtimestamp(timestamp_price/1000)
            volume = volumes[i][1] if i < len(volumes) else 0
            
            # Add to data
            df_data.append({
                'timestamp': date,
                'close': price,
                'volume': volume
            })
        
        # Create DataFrame
        df = pd.DataFrame(df_data)
        
        # Calculate high and low based on daily movement
        if len(df) > 1:
            # Estimate high as 2% above close and low as 2% below close
            df['high'] = df['close'] * 1.02  
            df['low'] = df['close'] * 0.98   
        
        # Set timestamp as index
        df = df.set_index('timestamp')
        
        # Cache the data
        if not df.empty:
            # Konversi DataFrame ke format yang JSON-serializable
            df_dict = df.reset_index().to_dict(orient='records')
            
            # Konversi objek Timestamp menjadi string
            for record in df_dict:
                if 'timestamp' in record and isinstance(record['timestamp'], (pd.Timestamp, datetime)):
                    record['timestamp'] = record['timestamp'].isoformat()
            
            with open(cache_file, 'w') as f:
                json.dump(df_dict, f)
            logger.info(f"Cached data for {coin_id}")
        
        return df
        
    except Exception as e:
        logger.error(f"Error fetching data: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        return pd.DataFrame()


if __name__ == "__main__":
    # Test the collector
    collector = CoinGeckoCollector(rate_limit=3)
    
    if collector.ping_api():
        print("CoinGecko API is available")
        
        # Collect data with smaller limits for testing
        success = collector.collect_all_data(limit=10, detail_limit=3, include_categories=False)
        
        if success:
            print("Data collection successful!")
        else:
            print("Data collection failed")
    else:
        print("CoinGecko API is not available")