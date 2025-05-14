import os
import re
import json
import logging
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from typing import Dict, List, Tuple, Optional, Any, Union
import sys

# Tambahkan path root ke sys.path
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import RAW_DIR, PROCESSED_DIR, USER_PERSONAS, EVAL_RANDOM_SEED

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class DataProcessor:
    """
    Class untuk memproses data cryptocurrency untuk sistem rekomendasi
    """
    
    def __init__(self):
        # Pastikan direktori ada
        os.makedirs(PROCESSED_DIR, exist_ok=True)
        
        # Definisikan mapping kategori utama untuk normalisasi
        self.category_mappings = {
            # Layer dan Infrastruktur
            'layer-1': [
                'layer-1', 'layer1', 'l1', 'smart-contract-platform', 'blockchain-service',
                'proof-of-work-pow', 'proof-of-stake-pos', 'sidechain',
                'infrastructure', 'modular-blockchain'
            ],
            'layer-2': [
                'layer-2', 'layer2', 'l2', 'scaling', 'rollup', 'optimistic-rollups', 'zk-rollups',
                'zero-knowledge', 'zk', 'zero knowledge (zk)', 'layer 2 (l2)', 'layer-2-scaling',
                'bitcoin-layer-2', 'superchain-ecosystem', 'data-availability'
            ],
            'layer-0': [
                'layer-0', 'layer0', 'l0', 'cross-chain-communication', 'interoperability',
                'blockchain-interoperability', 'cross-chain'
            ],
            'layer-3': ['layer-3', 'layer3', 'l3'],
            
            # DeFi
            'defi': [
                'defi', 'decentralized-finance', 'decentralized-finance-defi', 'yield-farming', 
                'lending-borrowing', 'yield-aggregator', 'liquidity-protocol', 'dex',
                'defi-index', 'stablecoin-protocol', 'automated-market-maker-amm',
                'yield-optimizer', 'fixed-interest', 'btcfi', 'lsdfi', 'curve-ecosystem'
            ],
            'dex': [
                'dex', 'decentralized-exchange', 'dex-aggregator', 'swap', 'amm'
            ],
            'stablecoin': [
                'stablecoin', 'stablecoins', 'usd-stablecoin', 'eur-stablecoin', 
                'fiat-backed-stablecoin', 'crypto-backed-stablecoin', 'algorithmic-stablecoin',
                'commodity-backed-stablecoin', 'yield-bearing-stablecoins'
            ],
            'liquid-staking': [
                'liquid-staking', 'liquid-staking-tokens', 'liquid-staked-eth', 'liquid-staking-governance-tokens',
                'liquid-staked-btc', 'liquid-staked-sol', 'liquid-staked-apt', 'liquid-staked-sui'
            ],
            'restaking': [
                'restaking', 'liquid-restaking-tokens', 'liquid-restaked-eth', 'liquid-restaking-governance-token'
            ],
            'lending': [
                'lending', 'lending-borrowing', 'borrowing', 'credit', 'loan'
            ],
            'yield': [
                'yield-farming', 'yield-aggregator', 'yield-optimizer', 'yield-tokenization',
                'yield-tokenization-product', 'farming-as-a-service'
            ],
            
            # Collectibles & Gaming
            'nft': [
                'nft', 'non-fungible-tokens', 'non-fungible-tokens-nft', 'collectibles', 'digital-art',
                'generative-art', 'nfts', 'nfts-marketplace', 'nft-marketplace', 'nft-index',
                'nft-aggregator', 'nft-lending-borrowing', 'fractionalized-nft', 'nft-derivatives',
                'nft-launchpad', 'nft-amm', 'nftfi'
            ],
            'gaming': [
                'gaming', 'play-to-earn', 'p2e', 'game', 'metaverse', 'gaming-guild',
                'gaming-blockchains', 'gaming-marketplace', 'gaming (gamefi)', 'play to earn',
                'gamefi', 'game-fi', 'gaming-utility-token', 'gaming-governance-token',
                'gaming-platform', 'rpg', 'simulation-games', 'massively-multiplayer-online-mmo',
                'racing-games', 'fighting-games', 'card-games', 'strategy-games',
                'adventure-games', 'arcade-games', 'sports-games', 'on-chain-gaming',
                'animal-racing', 'farming-games', 'shooting-games'
            ],
            'metaverse': [
                'metaverse', 'virtual-reality', 'augmented-reality', 'virtual-world'
            ],
            
            # AI & Tech
            'ai': [
                'ai', 'artificial-intelligence', 'artificial intelligence', 'artificial-intelligence-ai',
                'artificial intelligence (ai)', 'machine-learning', 'large-language-models', 'llm',
                'ai-framework', 'ai-applications', 'ai-agents', 'defai', 'ai-meme-coins'
            ],
            'privacy': [
                'privacy-coins', 'privacy-blockchain', 'vpn', 'privacy-protocol', 'privacy-token'
            ],
            'identity': [
                'identity', 'name-service', 'digital-identity', 'identity-verification'
            ],
            'iot': [
                'internet-of-things-iot', 'iot', 'depin', 'mobile-mining'
            ],
            'storage': [
                'storage', 'decentralized-storage', 'file-storage', 'cloud-storage'
            ],
            
            # Meme & Social Tokens
            'meme': [
                'meme', 'meme-token', 'dog-themed-coins', 'elon-musk-inspired-coins',
                'frog-themed-coins', 'cat-themed-coins', 'duck-themed-coins',
                'memes', 'parody-meme-coins', 'ai-meme-coins', 'solana-meme-coins',
                'sui-meme', 'tron-meme', 'base-meme-coins', 'mascot-themed',
                'wall-street-bets-themed', 'zodiac-themed', 'emoji-themed',
                'sticker-themed-coin', 'christmas-themed', 'chinese-meme'
            ],
            'exchange-token': [
                'exchange', 'exchange-token', 'exchange-based-tokens', 'centralized-exchange-token-cex',
                'dex', 'decentralized-exchange', 'cefi'
            ],
            'fan-token': [
                'fan-token', 'sports', 'gaming-guild', 'guild-scholarship', 'celebrity-themed-coins'
            ],
            'social': [
                'socialfi', 'social-token', 'social-media', 'friend-tech', 'farcaster-ecosystem',
                'telegram_apps'
            ],
            
            # Real World Assets
            'rwa': [
                'real-world-assets-rwa', 'asset-backed-tokens', 'wrapped-tokens',
                'tokenized-products', 'tokenized-commodities', 'tokenized-gold',
                'tokenized-silver', 'tokenized-stock', 'tokenized-treasury-bonds-t',
                'rwa-protocol', 'realt-tokens'
            ],
            'real-estate': [
                'real-estate', 'property', 'realt-tokens', 'real-estate-token'
            ],
            
            # Other Categories
            'bridge': [
                'bridged-tokens', 'bridged-stablecoins', 'bridged-usdt', 'bridged-usdc',
                'bridged-weth', 'bridged-wbtc', 'bridge-governance-tokens', 'bridged-frax',
                'bridged-wbnb', 'bridged-wavax', 'bridged-wsteth', 'cross-chain'
            ],
            'dao': [
                'dao', 'decentralized-autonomous-organization', 'governance', 'metagovernance'
            ],
            'oracle': [
                'oracle', 'data-oracle', 'price-oracle'
            ],
            'prediction': [
                'prediction-markets', 'forecasting', 'prediction-protocol', 'betting',
                'gambling'
            ],
            'token-standard': [
                'token-standards', 'erc-404', 'brc-20', 'drc-20', 'asc-20',
                'src-20', 'erc20i', 'hybrid-token-standards', 'token-2022'
            ],
            'launchpad': [
                'launchpad', 'tokenfi-launchpad', 'chaingpt-pad', 'camelot-launchpad',
                'poolz-finance-launchpad', 'hyperxpad-launchpad', 'bitstarters-launchpad'
            ],
            'tourism': [
                'tourism', 'travel', 'vacation', 'hospitality'
            ],
            'charity': [
                'charity', 'donations', 'philanthropy', 'non-profit'
            ],
            'energy': [
                'energy', 'renewable-energy', 'electricity', 'power'
            ],
            'sports': [
                'sports', 'sports-games', 'fan-token', 'sports-betting'
            ],
            'education': [
                'education', 'learning', 'training', 'academic'
            ],
            'science': [
                'decentralized-science-desci', 'science', 'research', 'desci-meme'
            ],
            'insurance': [
                'insurance', 'risk-protection', 'coverage'
            ],
            'marketing': [
                'marketing', 'advertisement', 'promotion'
            ],
            'music': [
                'music', 'audio', 'sound', 'entertainment'
            ],
            'healthcare': [
                'healthcare', 'medical', 'wellness', 'health'
            ],
            'legal': [
                'legal', 'law', 'compliance', 'regulation'
            ],
            'eco-friendly': [
                'eco-friendly', 'green', 'sustainable', 'environmental'
            ],
            
            # Ecosistemas de Blockchain específicos
            'ethereum-ecosystem': ['ethereum-ecosystem', 'ethereum'],
            'binance-smart-chain': ['binance-smart-chain', 'bsc', 'bnb-chain-ecosystem', 'bnb'],
            'solana-ecosystem': ['solana-ecosystem', 'solana', 'sol'],
            'polygon-ecosystem': ['polygon-ecosystem', 'polygon', 'matic'],
            'avalanche-ecosystem': ['avalanche-ecosystem', 'avalanche', 'avax'],
            'arbitrum-ecosystem': ['arbitrum-ecosystem', 'arbitrum'],
            'optimism-ecosystem': ['optimism-ecosystem', 'optimism', 'op'],
            'base-ecosystem': ['base-ecosystem', 'base'],
            'fantom-ecosystem': ['fantom-ecosystem', 'fantom', 'ftm'],
            'cosmos-ecosystem': ['cosmos-ecosystem', 'cosmos', 'atom'],
            'tron-ecosystem': ['tron-ecosystem', 'tron', 'trx'],
            'cardano-ecosystem': ['cardano-ecosystem', 'cardano', 'ada'],
            'bitcoin-ecosystem': ['bitcoin-ecosystem', 'bitcoin', 'btc'],
            'sui-ecosystem': ['sui-ecosystem', 'sui'],
            'near-protocol-ecosystem': ['near-protocol-ecosystem', 'near'],
            'aptos-ecosystem': ['aptos-ecosystem', 'aptos', 'apt'],
            'ton-ecosystem': ['ton-ecosystem', 'ton'],
            'blast-ecosystem': ['blast-ecosystem', 'blast'],
            'starknet-ecosystem': ['starknet-ecosystem', 'starknet'],
        }
        
        # Lista de categori ecosystem
        self.ecosystem_categories = [
            'ethereum-ecosystem', 'binance-smart-chain', 'solana-ecosystem', 'polygon-ecosystem',
            'avalanche-ecosystem', 'arbitrum-ecosystem', 'optimism-ecosystem', 'base-ecosystem',
            'fantom-ecosystem', 'cosmos-ecosystem', 'tron-ecosystem', 'cardano-ecosystem', 
            'bitcoin-ecosystem', 'sui-ecosystem', 'near-protocol-ecosystem', 'aptos-ecosystem',
            'ton-ecosystem', 'blast-ecosystem', 'starknet-ecosystem', 'xdai-ecosystem',
            'osmosis-ecosytem', 'cronos-ecosystem', 'chiliz-ecosystem', 'harmony-ecosystem',
            'energi-ecosystem', 'linea-ecosystem', 'klaytn-ecosystem', 'sonic-ecosystem',
            'bitcichain-ecosystem', 'hedera-ecosystem', 'algorand-ecosystem', 'mantle-ecosystem',
            'xrp-ledger-ecosystem', 'metis-ecosystem', 'moonriver-ecosystem', 'tezos-ecosystem',
            'sora-ecosystem', 'berachain-ecosystem', 'multiversx-ecosystem', 'scroll-ecosystem',
            'stellar-ecosystem'
        ]
        
        # Definisikan prioritas kategori - REVISI BESAR DISINI
        self.category_priority = [
            # Top priorities - Kategori Utama
            'layer-2',       # Layer-2 scaling solutions (prioritas tertinggi)
            'layer-1',       # Layer-1 chains 
            'defi',          # DeFi projects
            'dex',           # DEX platforms
            'stablecoin',    # Stablecoins
            'liquid-staking', # Liquid staking
            'restaking',     # Restaking protocols
            'lending',       # Lending platforms
            'yield',         # Yield farming/aggregators
            'nft',           # NFT projects
            'gaming',        # Gaming/GameFi
            'metaverse',     # Metaverse projects
            'ai',            # AI projects
            'meme',          # Meme tokens
            'rwa',           # Real world assets
            'exchange-token', # Exchange tokens
            'bridge',        # Bridge protocols
            'privacy',       # Privacy coins/protocols
            'token-standard', # Token standards
            
            # Secondary categories - Kategori Sekunder
            'oracle',        # Oracles
            'dao',           # DAOs
            'launchpad',     # Launchpads
            'prediction',    # Prediction markets
            'identity',      # Identity solutions
            'storage',       # Storage solutions
            'iot',           # IoT projects
            'layer-0',       # Layer-0/Cross-chain
            'layer-3',       # Layer-3 solutions
            'fan-token',     # Fan tokens
            'social',        # Social tokens/platforms
            'tourism',       # Tourism related
            'healthcare',    # Healthcare
            'education',     # Education
            'legal',         # Legal
            'insurance',     # Insurance
            'marketing',     # Marketing
            'charity',       # Charity
            'energy',        # Energy
            'sports',        # Sports
            'music',         # Music
            'real-estate',   # Real estate
            'science',       # Science
            'eco-friendly',  # Eco-friendly
            
            # Ecosystem categories - Prioritas terendah (hanya jika tidak ada yang lain)
            'ethereum-ecosystem',
            'binance-smart-chain',
            'solana-ecosystem',
            'polygon-ecosystem',
            'avalanche-ecosystem',
            'arbitrum-ecosystem',
            'optimism-ecosystem',
            'base-ecosystem',
            'other-ecosystem'  # Fallback for other ecosystems
        ]
        
        # Definisikan platform mapping untuk normalisasi chain
        self.blockchain_platforms = {
            'ethereum': ['eth', 'erc20', 'erc-20', 'erc721', 'erc-721', 'ethereum-ecosystem'],
            'binance-smart-chain': ['bnb', 'bsc', 'bep20', 'bep-20', 'bnb-chain-ecosystem'],
            'solana': ['sol', 'spl', 'solana-ecosystem'],
            'polygon-pos': ['polygon', 'matic', 'polygon-ecosystem'],
            'avalanche': ['avax', 'avalanche-ecosystem'],
            'tron': ['trx', 'trc20', 'trc-20', 'tron-ecosystem'],
            'cardano': ['ada', 'cardano-ecosystem'],
            'arbitrum': ['arb', 'arbitrum-ecosystem'],
            'optimism': ['op', 'optimism-ecosystem'],
            'base': ['base-ecosystem'],
            'sui': ['sui-ecosystem'],
            'near': ['near-protocol-ecosystem'],
            'aptos': ['apt', 'aptos-ecosystem'],
            'ton': ['ton-ecosystem'],
            'bitcoin': ['btc', 'bitcoin-ecosystem', 'brc-20'],
            'cosmos': ['atom', 'cosmos-ecosystem'],
            'blast': ['blast-ecosystem'],
            'fantom': ['ftm', 'fantom-ecosystem'],
        }
    
    def load_latest_data(self) -> Tuple[Optional[pd.DataFrame], Optional[pd.DataFrame], Optional[pd.DataFrame]]:
        logger.info("Loading raw data")
        
        # Check for combined file first
        combined_files = [f for f in os.listdir(RAW_DIR) if f.startswith("combined_coins_") and f.endswith(".json")]
        
        if combined_files:
            # Use the latest combined file
            latest_combined = sorted(combined_files)[-1]
            combined_path = os.path.join(RAW_DIR, latest_combined)
            
            logger.info(f"Using latest combined file: {latest_combined}")
            
            try:
                with open(combined_path, 'r', encoding='utf-8') as f:
                    combined_data = json.load(f)
                
                market_df = pd.DataFrame(combined_data)
                logger.info(f"Loaded {len(market_df)} entries from combined file")
                
                # Ensure no duplicates
                market_df = market_df.drop_duplicates(subset='id')
                logger.info(f"After removing duplicates: {len(market_df)} entries")
                
            except Exception as e:
                logger.error(f"Error loading combined file: {e}")
                market_df = None
        else:
            # No combined file, use individual market files
            logger.info("No combined file found, loading individual market files")
            
            # Mencari file market data terbaru
            market_files = [f for f in os.listdir(RAW_DIR) if f.startswith("coins_markets_") and f.endswith(".json")]
            
            if not market_files:
                logger.error("No market data files found")
                return None, None, None
            
            # Mengumpulkan semua data market
            all_market_data = []
            for file in market_files:
                try:
                    with open(os.path.join(RAW_DIR, file), 'r', encoding='utf-8') as f:
                        data = json.load(f)
                        if isinstance(data, list):
                            # Extract category from filename if possible
                            category = None
                            if '_all_' not in file and '_page' not in file:
                                # Try to extract category from filename (e.g. coins_markets_defi_20250418.json -> defi)
                                match = re.search(r'coins_markets_([a-zA-Z0-9-]+)_\d+', file)
                                if match and match.group(1) != 'all':
                                    category = match.group(1)
                                    logger.info(f"Extracted category '{category}' from filename: {file}")
                            
                            # Add category info if available and not already present
                            if category:
                                for item in data:
                                    if 'query_category' not in item:
                                        item['query_category'] = category
                            
                            all_market_data.extend(data)
                except Exception as e:
                    logger.error(f"Error loading market data file {file}: {e}")
            
            if not all_market_data:
                logger.error("No market data loaded")
                return None, None, None
            
            # Membuat DataFrame market dan menghapus duplikat
            market_df = pd.DataFrame(all_market_data)
            
            # Log kolom yang tersedia untuk debugging
            logger.info(f"Market data columns: {market_df.columns.tolist()}")
            
            # Hapus duplikat berdasarkan id
            original_len = len(market_df)
            market_df = market_df.drop_duplicates(subset='id')
            logger.info(f"Removed {original_len - len(market_df)} duplicate entries")
        
        # Add query_category if missing
        if 'query_category' not in market_df.columns:
            logger.warning("query_category column not found, adding default value")
            market_df['query_category'] = 'unknown'
        
        # Load detail coin
        detail_files = [f for f in os.listdir(RAW_DIR) if f.startswith("coin_details_") and f.endswith(".json")]
        detailed_data = []
        
        for file in detail_files:
            try:
                with open(os.path.join(RAW_DIR, file), 'r', encoding='utf-8') as f:
                    data = json.load(f)
                
                # Extract coin ID from filename
                coin_id = file.replace("coin_details_", "").replace(".json", "")
                
                # Extract relevant details
                community_data = data.get('community_data', {}) or {}
                developer_data = data.get('developer_data', {}) or {}
                
                detailed_info = {
                    'id': coin_id,
                    'platforms': data.get('platforms', {}),
                    'categories': data.get('categories', []),
                    'telegram_channel_user_count': community_data.get('telegram_channel_user_count', 0),
                    'twitter_followers': community_data.get('twitter_followers', 0),
                    'github_stars': developer_data.get('stars', 0),
                    'github_subscribers': developer_data.get('subscribers', 0),
                    'github_forks': developer_data.get('forks', 0),
                    'description': data.get('description', {}).get('en', ''),
                    'genesis_date': data.get('genesis_date'),
                    'sentiment_votes_up_percentage': data.get('sentiment_votes_up_percentage', 50)
                }
                detailed_data.append(detailed_info)
            except Exception as e:
                logger.error(f"Error processing detail file {file}: {e}")
        
        # Buat DataFrame detail
        detailed_df = pd.DataFrame(detailed_data) if detailed_data else None
        if detailed_df is not None:
            logger.info(f"Loaded detailed data for {len(detailed_df)} coins")
        
        # Load kategori
        categories_df = None
        if os.path.exists(os.path.join(RAW_DIR, "coins_categories.json")):
            try:
                with open(os.path.join(RAW_DIR, "coins_categories.json"), 'r', encoding='utf-8') as f:
                    categories_data = json.load(f)
                categories_df = pd.DataFrame(categories_data)
                logger.info(f"Loaded {len(categories_df)} categories")
            except Exception as e:
                logger.error(f"Error loading categories data: {e}")
        
        # Load trending
        trending_df = None
        if os.path.exists(os.path.join(RAW_DIR, "trending_coins.json")):
            try:
                with open(os.path.join(RAW_DIR, "trending_coins.json"), 'r', encoding='utf-8') as f:
                    trending_data = json.load(f)
                if 'coins' in trending_data:
                    trending_coins = [item['item'] for item in trending_data['coins']]
                    trending_df = pd.DataFrame(trending_coins)
                    logger.info(f"Loaded {len(trending_df)} trending coins")
            except Exception as e:
                logger.error(f"Error loading trending data: {e}")
        
        # Merge market data dengan detail data
        projects_df = None
        if detailed_df is not None and not detailed_df.empty:
            projects_df = market_df.merge(detailed_df, on='id', how='left', suffixes=('', '_detailed'))
            logger.info(f"Merged market and detailed data: {len(projects_df)} rows")
        else:
            projects_df = market_df
            logger.info(f"Using market data only: {len(projects_df)} rows")
        
        # Verifikasi apakah ada kolom yang diharapkan tapi hilang
        expected_columns = [
            'id', 'symbol', 'name', 'image', 'current_price', 'market_cap',
            'total_volume', 'price_change_percentage_24h', 
            'price_change_percentage_7d_in_currency',
            'query_category'
        ]
        
        missing_columns = [col for col in expected_columns if col not in projects_df.columns]
        if missing_columns:
            logger.warning(f"Missing expected columns: {missing_columns}")
            
            # Add missing columns with default values
            for col in missing_columns:
                if col == 'query_category':
                    projects_df[col] = 'unknown'
                else:
                    projects_df[col] = None
        
        return projects_df, categories_df, trending_df
    
    def validate_processed_data(self, projects_df: pd.DataFrame) -> pd.DataFrame:
        logger.info("Validating processed data")
        
        validation_results = {
            "total_projects": len(projects_df),
            "issues_found": 0,
            "issues_details": []
        }
        
        # 1. Cek format JSON untuk platforms dan categories
        json_issues = 0
        for idx, row in projects_df.iterrows():
            # Cek platforms
            if 'platforms' in projects_df.columns:
                platforms = row['platforms']
                if not isinstance(platforms, dict) and not pd.isna(platforms):
                    json_issues += 1
                    validation_results["issues_details"].append({
                        "id": row.get('id', f"Row {idx}"),
                        "issue": "platforms is not a valid dictionary",
                        "value": str(platforms)[:100]
                    })
            
            # Cek categories
            if 'categories' in projects_df.columns:
                categories = row['categories']
                if not isinstance(categories, list) and not pd.isna(categories):
                    json_issues += 1
                    validation_results["issues_details"].append({
                        "id": row.get('id', f"Row {idx}"),
                        "issue": "categories is not a valid list",
                        "value": str(categories)[:100]
                    })
        
        validation_results["json_format_issues"] = json_issues
        validation_results["issues_found"] += json_issues
        
        # 2. Cek konsistensi antara primary_category dan categories
        category_mismatch = 0
        for idx, row in projects_df.iterrows():
            if 'primary_category' in projects_df.columns and 'categories' in projects_df.columns:
                primary_cat = row['primary_category']
                categories = row['categories'] if isinstance(row['categories'], list) else []
                
                if categories and primary_cat not in ['unknown', 'other-ecosystem']:
                    # Cek apakah primary_category match dengan salah satu kategori asli
                    # atau setidaknya sebagian dari kategori asli
                    normalized_categories = [cat.lower() for cat in categories if cat]
                    found_match = False
                    
                    # Cek match eksak
                    if primary_cat in normalized_categories:
                        found_match = True
                    else:
                        # Cek partial match
                        for cat in normalized_categories:
                            if primary_cat in cat or cat in primary_cat:
                                found_match = True
                                break
                            
                            # Cek jika primary_cat adalah ecosystem tapi disingkat
                            if primary_cat in ['ethereum', 'binance', 'solana', 'polygon', 'avalanche', 
                                            'arbitrum', 'optimism', 'base', 'fantom', 'cosmos']:
                                ecosystem_cat = f"{primary_cat}-ecosystem"
                                if ecosystem_cat in cat or cat in ecosystem_cat:
                                    found_match = True
                                    break
                    
                    # Jika tidak ada match, catat masalah
                    if not found_match:
                        category_mismatch += 1
                        validation_results["issues_details"].append({
                            "id": row.get('id', f"Row {idx}"),
                            "issue": "primary_category doesn't match any category in categories",
                            "primary_category": primary_cat,
                            "categories": str(categories)
                        })
        
        validation_results["category_mismatch_issues"] = category_mismatch
        validation_results["issues_found"] += category_mismatch
        
        # 3. Cek nilai yang kosong untuk field penting
        null_issues = 0
        important_fields = ['id', 'name', 'symbol', 'current_price', 'market_cap', 'total_volume']
        
        for field in important_fields:
            if field in projects_df.columns:
                null_count = projects_df[field].isna().sum()
                if null_count > 0:
                    null_issues += null_count
                    validation_results["issues_details"].append({
                        "issue": f"Null values in {field}",
                        "count": null_count
                    })
        
        validation_results["null_value_issues"] = null_issues
        validation_results["issues_found"] += null_issues
        
        # 4. Logika validasi khusus untuk menunjukkan contoh mismatch
        # Tunjukkan 5 baris acak untuk diperiksa
        if len(projects_df) > 0:
            sample_rows = projects_df.sample(min(5, len(projects_df)))
            validation_results["sample_rows"] = []
            
            for _, row in sample_rows.iterrows():
                sample = {
                    "id": row.get('id', 'N/A'),
                    "name": row.get('name', 'N/A'),
                    "symbol": row.get('symbol', 'N/A'),
                    "primary_category": row.get('primary_category', 'N/A'),
                    "categories": str(row.get('categories', [])),
                }
                validation_results["sample_rows"].append(sample)
        
        # Log hasil validasi
        logger.info(f"Validation completed. Found {validation_results['issues_found']} issues.")
        if validation_results['issues_found'] > 0:
            logger.warning(f"JSON format issues: {validation_results['json_format_issues']}")
            logger.warning(f"Category mismatch issues: {validation_results['category_mismatch_issues']}")
            logger.warning(f"Null value issues: {validation_results['null_value_issues']}")
            
            # TAMBAHKAN KODE INI UNTUK DETAIL SEMUA MASALAH
            if validation_results['issues_found'] > 0:
                logger.warning("Detail semua masalah validasi:")
                for i, issue in enumerate(validation_results["issues_details"], 1):
                    issue_type = issue.get("issue", "Unknown issue")
                    project_id = issue.get("id", "Unknown")
                    logger.warning(f"  Issue #{i}: {issue_type} - Project: {project_id}")
                    
                    # Log detail tambahan berdasarkan jenis masalah
                    if "primary_category doesn't match" in issue_type:
                        logger.warning(f"    Primary: {issue.get('primary_category', 'N/A')}")
                        logger.warning(f"    Categories: {issue.get('categories', 'N/A')}")
                    elif "not a valid" in issue_type:
                        logger.warning(f"    Value: {issue.get('value', 'N/A')}")
        
        return validation_results
    
    def process_data(self, n_users: int = 500) -> Tuple[pd.DataFrame, pd.DataFrame, pd.DataFrame]:
        logger.info("Processing data")
        
        # Load data mentah
        projects_df, categories_df, trending_df = self.load_latest_data()
        
        if projects_df is None or projects_df.empty:
            raise ValueError("No project data available")
            
        # 1. Bersihkan dan lengkapi data proyek
        projects_df = self._clean_project_data(projects_df, trending_df)
        
        # 2. TAMBAHAN: Simpan categories_list sebelum membuat primary_category
        # Ini memastikan kita memiliki akses ke kategori asli dalam format terstruktur
        if 'categories' in projects_df.columns:
            def process_categories(cats):
                if isinstance(cats, list):
                    return cats
                elif isinstance(cats, str) and cats.startswith('[') and cats.endswith(']'):
                    try:
                        return json.loads(cats)
                    except:
                        return [cats]
                elif isinstance(cats, str):
                    return [cats]
                return []
            
            projects_df['categories_list'] = projects_df['categories'].apply(process_categories)
        
        # 3. Buat fitur untuk rekomendasi
        features_df = self._create_features(projects_df)
        
        # 4. Buat data interaksi sintetis
        interactions_df = self._create_synthetic_interactions(projects_df, n_users)
        
        # 5. Validasi hasil pemrosesan
        validation_results = self.validate_processed_data(projects_df)
        
        # Log hasil validasi 
        logger.info(f"Data validation results: {validation_results['issues_found']} issues found.")
        
        # Jika ada masalah serius, tampilkan log peringatan
        if validation_results['issues_found'] > 100:
            logger.warning(f"High number of data issues detected: {validation_results['issues_found']}")
            logger.warning("Please check data integrity before proceeding")
        
        # 6. Simpan data yang sudah diproses
        self._save_processed_data(projects_df, interactions_df, features_df)
        
        return projects_df, interactions_df, features_df
    
    def _clean_project_data(self, projects_df: pd.DataFrame, trending_df: Optional[pd.DataFrame] = None) -> pd.DataFrame:
        logger.info("Cleaning project data")
        
        # Buat salinan untuk dimodifikasi
        df = projects_df.copy()
        
        # Tangani nilai NaN dengan nilai default
        df['market_cap'] = df['market_cap'].fillna(0)
        df['total_volume'] = df['total_volume'].fillna(0)
        df['current_price'] = df['current_price'].fillna(0)
        
        # Pastikan kolom numerical ada
        for col in ['price_change_percentage_24h', 'price_change_percentage_7d_in_currency', 
                    'price_change_percentage_30d_in_currency', 'price_change_percentage_1h_in_currency']:
            if col in df.columns:
                df[col] = df[col].fillna(0)
            else:
                df[col] = 0
        
        # Tangani kolom sosial dengan nilai default
        for col in ['twitter_followers', 'github_stars', 
                'github_subscribers', 'github_forks', 'telegram_channel_user_count']:
            if col in df.columns:
                df[col] = df[col].fillna(0).astype(int)
            else:
                df[col] = 0
        
        # Standarisasi platforms dan categories
        if 'platforms' in df.columns:
            # Jika platforms adalah string, konversi ke dict
            if df['platforms'].dtype == 'object' and isinstance(df['platforms'].iloc[0], str):
                # Fix untuk double quotes: ""ethereum"" -> "ethereum"
                df['platforms'] = df['platforms'].apply(
                    lambda x: json.loads(x.replace('""', '"')) if isinstance(x, str) else x
                )
            # Pastikan nilai None diganti dengan dict kosong
            df['platforms'] = df['platforms'].fillna({})
        else:
            df['platforms'] = [{} for _ in range(len(df))]
                    
        # Tangani kolom categories dengan cara yang lebih aman
        if 'categories' in df.columns:
            def clean_categories(x):
                if isinstance(x, (list, np.ndarray)):
                    return x
                if pd.isna(x) or x is None:
                    return []
                if isinstance(x, str):
                    try:
                        # Fix untuk double quotes: ""NFT"" -> "NFT"
                        return json.loads(x.replace('""', '"'))
                    except:
                        return []
                return []
                    
            df['categories'] = df['categories'].apply(clean_categories)
            
            # TAMBAHAN: Buat categories_list yang tersusun rapi
            df['categories_list'] = df['categories'].apply(lambda x: x if isinstance(x, list) else [])
        else:
            df['categories'] = [[] for _ in range(len(df))]
            df['categories_list'] = [[] for _ in range(len(df))]
        
        # Ekstrak primary_category dengan metode yang disederhanakan
        df['primary_category'] = df.apply(self._extract_primary_category_improved, axis=1)
        
        # Ekstrak chain
        df['chain'] = df.apply(lambda row: self._extract_primary_chain(row['platforms']), axis=1)
        
        # Hitung skor popularitas dan tren
        df = self._calculate_metrics(df)
        
        # Pastikan kolom deskripsi ada dengan nilai default
        if 'description' not in df.columns:
            df['description'] = ''
        else:
            df['description'] = df['description'].fillna('')
        
        # Pastikan kolom tanggal ada dengan nilai default
        if 'genesis_date' not in df.columns:
            df['genesis_date'] = None
        else:
            df['genesis_date'] = pd.to_datetime(df['genesis_date'], errors='coerce')
        
        # Pastikan nilai default untuk kolom binary/boolean
        if 'is_trending' not in df.columns:
            df['is_trending'] = 0
        
        # Tambahkan skor trending jika ada data trending
        if trending_df is not None and not trending_df.empty:
            trending_ids = trending_df['id'].tolist() if 'id' in trending_df.columns else []
            df['is_trending'] = df['id'].apply(lambda x: 1 if x in trending_ids else 0)
            # Boost trend score untuk koin trending
            df.loc[df['is_trending'] == 1, 'trend_score'] += 30
        
        # Pastikan semua kolom yang dibutuhkan tersedia
        required_columns = [
            'id', 'name', 'symbol', 'primary_category',
            'image', 'current_price', 'market_cap', 'total_volume'
        ]
        
        # Tambahkan nilai default untuk kolom yang kosong
        for col in required_columns:
            if col not in df.columns:
                if col in ['market_cap', 'current_price', 'total_volume']:
                    df[col] = 0
                else:
                    df[col] = 'unknown'
        
        return df
        
    def _extract_primary_category_improved(self, row) -> str:
        """
        Ekstrak kategori utama dari daftar kategori dengan pendekatan yang lebih sederhana dan cerdas.
        Mengembalikan string kategori tunggal, bukan array.
        """
        categories = row.get('categories', [])
        query_category = row.get('query_category')
        
        # Handle empty categories
        if not categories or not any(isinstance(cat, str) and cat.strip() for cat in categories):
            # Fallback to query_category jika tersedia dan bukan kategori generik
            if query_category and query_category.lower() not in ['unknown', 'top']:
                return query_category.lower()
            return 'unknown'
        
        # Normalisasi kategori (filter hanya string dan lowercase)
        normalized_categories = [cat.lower() for cat in categories if isinstance(cat, str) and cat]
        
        if not normalized_categories:
            return 'unknown'
        
        # SMART DETECTION: Gunakan beberapa faktor untuk menentukan kategori utama
        
        # 1. Kategori paling spesifik biasanya lebih penting
        # Kategori dengan nama lebih panjang dan lebih spesifik cenderung lebih informatif
        specific_score = [(cat, len(cat.split('-')) + len(cat.split(' '))) for cat in normalized_categories]
        specific_score.sort(key=lambda x: x[1], reverse=True)
        
        # 2. Kategori yang lebih relevan diberi prioritas
        # Beberapa kategori lebih relevan untuk rekomendasi cryptocurrency
        high_relevance_prefixes = ['defi', 'layer', 'nft', 'dex', 'stablecoin', 'lending', 'yield', 'dao']
        
        for cat in normalized_categories:
            for prefix in high_relevance_prefixes:
                if cat.startswith(prefix) or f"-{prefix}" in cat:
                    return cat  # Langsung kembalikan kategori yang sangat relevan
        
        # 3. Hindari kategori ekosistem blockchain kecuali hanya itu yang tersedia
        ecosystem_categories = [cat for cat in normalized_categories 
                            if 'ecosystem' in cat or cat in self.ecosystem_categories]
        non_ecosystem_categories = [cat for cat in normalized_categories 
                                if cat not in ecosystem_categories]
        
        # 4. Pilih kategori prioritas tertinggi
        # Jika ada kategori non-ekosistem, pilih yang paling spesifik
        if non_ecosystem_categories:
            # Urutkan berdasarkan spesifisitas
            specific_non_eco = [(cat, len(cat.split('-')) + len(cat.split(' '))) 
                            for cat in non_ecosystem_categories]
            specific_non_eco.sort(key=lambda x: x[1], reverse=True)
            return specific_non_eco[0][0]
        
        # 5. Jika hanya ada kategori ekosistem, gunakan itu
        if ecosystem_categories:
            return ecosystem_categories[0]
        
        # 6. Jika semua gagal, gunakan kategori pertama
        return normalized_categories[0]
    
    def _extract_primary_chain(self, platforms: Dict[str, str]) -> str:
        # Handle NaN or non-dict values
        if pd.isna(platforms) or not isinstance(platforms, dict):
            return 'unknown'
                
        if not platforms:
            return 'unknown'
                
        # Prioritas chain
        priority_chains = ['ethereum', 'binance-smart-chain', 'solana', 'polygon-pos', 'avalanche']
        
        # Cek apakah ada priority chain yang match
        for chain in priority_chains:
            if chain in platforms:
                return chain
        
        # Cek menggunakan normalisasi dengan aliases
        platform_keys = [p.lower() for p in platforms.keys()]
        for chain_name, aliases in self.blockchain_platforms.items():
            for alias in aliases:
                if any(alias in p for p in platform_keys):
                    return chain_name
        
        # Jika tidak ada match, ambil platform pertama
        return list(platforms.keys())[0] if platforms else 'unknown'
    
    def _calculate_metrics(self, df: pd.DataFrame) -> pd.DataFrame:
        logger.info("Calculating additional metrics")
        
        # Create a copy to avoid modifying the original
        result_df = df.copy()
        
        # Helper function for winsorization to handle extreme values
        def winsorize(s, low=0.01, high=0.99):
            """Winsorize a series to limit extreme values"""
            q_low = s.quantile(low)
            q_high = s.quantile(high)
            return s.clip(lower=q_low, upper=q_high)
        
        # Ensure numeric columns are properly handled
        for col in ['market_cap', 'total_volume', 'twitter_followers', 
                'github_stars', 'telegram_channel_user_count']:
            if col in result_df.columns:
                result_df[col] = pd.to_numeric(result_df[col], errors='coerce').fillna(0)
        
        # 1. Enhanced Popularity Score - with outlier handling
        # Winsorize metrics to handle outliers before log transformation
        market_cap = winsorize(result_df['market_cap'].fillna(0))
        volume = winsorize(result_df['total_volume'].fillna(0))
        twitter = winsorize(result_df['twitter_followers'].fillna(0))
        github = winsorize(result_df['github_stars'].fillna(0))
        
        # Tambahkan metrics sosial media telegram
        telegram = winsorize(result_df['telegram_channel_user_count'].fillna(0)) if 'telegram_channel_user_count' in result_df.columns else 0
        
        # Apply logarithmic transformation with careful handling of zeros
        # Use log1p to handle zeros gracefully and scale appropriately
        log_market_cap = np.log1p(market_cap) / np.log(1e12)  # Scale relative to $1T
        log_volume = np.log1p(volume) / np.log(1e10)  # Scale relative to $10B
        log_twitter = np.log1p(twitter) / np.log(1e6)  # Scale relative to 1M followers
        log_github = np.log1p(github) / np.log(1e5)  # Scale relative to 100K stars
        
        # Log transform untuk metrics sosial telegram
        log_telegram = np.log1p(telegram) / np.log(1e5) if not isinstance(telegram, int) else 0
        
        # Bobot seimbang dengan penambahan metrik sosial
        total_social_weight = 0.40  # Total bobot untuk semua metrik sosial
        
        # Hitung bobot individual untuk metrik sosial berdasarkan ketersediaan
        available_social_metrics = 0
        for metric in [log_twitter, log_telegram]:
            if not isinstance(metric, int) or metric > 0:
                available_social_metrics += 1
        
        # Jika tidak ada metrik sosial, gunakan bobot default
        if available_social_metrics == 0:
            social_weights = {"twitter": 0, "telegram": 0}
        else:
            # Distribusikan bobot secara merata di antara metrik sosial yang tersedia
            weight_per_metric = total_social_weight / available_social_metrics
            social_weights = {
                "twitter": weight_per_metric if not isinstance(log_twitter, int) or log_twitter > 0 else 0,
                "telegram": weight_per_metric if not isinstance(log_telegram, int) or log_telegram > 0 else 0
            }
        
        # Hitung popularity score dengan metrik sosial
        popularity_score = (
            0.30 * log_market_cap +  # Fundamental ekonomi
            0.20 * log_volume +      # Aktivitas trading
            social_weights["twitter"] * log_twitter +
            social_weights["telegram"] * log_telegram +
            0.10 * log_github        # Aktivitas developer
        )
        
        # Scale to 0-100 using percentile ranking for more uniform distribution
        popularity_score_ranked = popularity_score.rank(pct=True) * 100
        result_df['popularity_score'] = popularity_score_ranked
        
        # 2. Trend Score - More balanced approach with time decay and outlier handling
        # Get price changes with better handling of missing values
        price_24h = result_df['price_change_percentage_24h'].fillna(0) / 100
        price_7d = result_df['price_change_percentage_7d_in_currency'].fillna(0) / 100
        price_30d = result_df['price_change_percentage_30d_in_currency'].fillna(0) / 100
        
        # Apply sigmoid transformation to handle extreme values 
        def sigmoid_transform(x, scale=5):
            return 2 / (1 + np.exp(-scale * x)) - 1
        
        # Transform price changes with sigmoid to limit extreme values
        price_24h_transformed = sigmoid_transform(price_24h)
        price_7d_transformed = sigmoid_transform(price_7d)
        price_30d_transformed = sigmoid_transform(price_30d)
        
        # Meningkatkan pengaruh perubahan jangka pendek untuk lebih aktual
        trend_score = (
            0.55 * price_24h_transformed +  # Increased from 0.5
            0.30 * price_7d_transformed +   # Same
            0.15 * price_30d_transformed    # Reduced from 0.2
        )
        
        # Scale to 0-100 with 50 as neutral
        result_df['trend_score'] = 50 + (trend_score * 50)
        
        # 3. Developer Activity Score - Improved calculation with more meaningful metrics
        if all(col in result_df.columns for col in ['github_stars', 'github_forks', 'github_subscribers']):
            # Create a composite score of all GitHub metrics
            github_stats = (
                np.log1p(result_df['github_stars']) * 0.5 + 
                np.log1p(result_df['github_forks']) * 0.3 +
                np.log1p(result_df['github_subscribers']) * 0.2
            )
            
            # Scale relative to the top projects, but avoid making the scale too concentrated
            # Use 90th percentile instead of max to avoid outlier influence
            ref_value = github_stats.quantile(0.9)
            
            if ref_value > 0:
                # Use a more gradual scaling function for better distribution
                dev_score = np.tanh(github_stats / ref_value * 2) * 100
                result_df['developer_activity_score'] = dev_score.clip(0, 100)
            else:
                result_df['developer_activity_score'] = 0
        else:
            result_df['developer_activity_score'] = 0
        
        # 4. Social Engagement Score - Improved calculation with better normalization
        if 'market_cap' in result_df.columns and result_df['market_cap'].max() > 0:
            # Calculate social following with all available social metrics
            social_sum = result_df['twitter_followers']
            
            # Tambahkan metrics telegram jika tersedia
            if 'telegram_channel_user_count' in result_df.columns:
                social_sum += result_df['telegram_channel_user_count']
            
            # Calculate market cap in millions with minimum threshold to avoid division by zero
            market_cap_millions = result_df['market_cap'] / 1_000_000
            market_cap_millions = market_cap_millions.clip(lower=0.01)  # Minimum threshold
            
            # Calculate ratio of social following to market cap (followers per $M)
            engagement_ratio = social_sum / market_cap_millions
            
            # Handle zero and extreme values
            engagement_ratio = winsorize(engagement_ratio)
            
            # Apply logarithmic scaling for better distribution
            engagement_log = np.log1p(engagement_ratio) 
            
            # Scale using percentile ranking for more uniform distribution
            engagement_ranked = engagement_log.rank(pct=True)
            
            # Scale to 0-100
            result_df['social_engagement_score'] = engagement_ranked * 100
        else:
            result_df['social_engagement_score'] = 50
        
        # 5. Description Length - Hanya satu metrik
        if 'description' in result_df.columns:
            # Hanya simpan panjang deskripsi
            result_df['description_length'] = result_df['description'].fillna('').apply(len)
        else:
            result_df['description_length'] = 0
        
        # 6. Age Days - More accurate calculation with better date handling
        if 'genesis_date' in result_df.columns:
            today = pd.Timestamp.now().date()
            # Convert to datetime more robustly
            result_df['genesis_date_parsed'] = pd.to_datetime(
                result_df['genesis_date'], errors='coerce'
            )
            
            # Calculate age in days with safer date subtraction
            result_df['age_days'] = result_df['genesis_date_parsed'].apply(
                lambda x: (today - x.date()).days if pd.notna(x) else 0
            )
            
            # Clean up temporary column
            result_df = result_df.drop(columns=['genesis_date_parsed'])
        else:
            result_df['age_days'] = 0
        
        # 7. Maturity Score - Improved calculation with more balanced components
        # Logarithmic transformation of age for better scaling of old vs. new projects
        if result_df['age_days'].max() > 0:
            age_log = np.log1p(result_df['age_days'])
            # Use rank-based normalization
            age_score = age_log.rank(pct=True)
        else:
            age_score = 0
        
        # Get normalized component scores
        dev_score = result_df['developer_activity_score'] / 100
        social_score = result_df['social_engagement_score'] / 100
        
        # Use description length for desc_score
        if result_df['description_length'].max() > 0:
            desc_score = result_df['description_length'].rank(pct=True) / 100
        else:
            desc_score = 0
        
        # Calculate maturity score with more balanced weights
        maturity_score = (
            0.25 * age_score +        # Reduced from 0.3
            0.35 * dev_score +        # Increased from 0.3
            0.25 * social_score +     # Increased from 0.2
            0.15 * desc_score         # Reduced from 0.2
        )
        
        # Scale to 0-100
        result_df['maturity_score'] = (maturity_score * 100).clip(0, 100)
        
        return result_df
    
    def _calculate_category_similarity(self, category1: str, category2: str) -> float:
        if category1 == category2:
            return 1.0
        
        # Check if categories are in the same group in our mappings
        for _, aliases in self.category_mappings.items():
            lower_aliases = [alias.lower() for alias in aliases]
            if category1.lower() in lower_aliases and category2.lower() in lower_aliases:
                return 0.8
        
        # Check ecosystem categories
        if (any(category1.lower() in eco.lower() for eco in self.ecosystem_categories) and 
            any(category2.lower() in eco.lower() for eco in self.ecosystem_categories)):
            return 0.7
        
        # Check if one category contains the other
        if category1.lower() in category2.lower() or category2.lower() in category1.lower():
            return 0.6
        
        # Calculate overlap in words
        words1 = set(category1.lower().replace('-', ' ').split())
        words2 = set(category2.lower().replace('-', ' ').split())
        
        if not words1 or not words2:
            return 0.0
        
        # Jaccard similarity
        intersection = len(words1.intersection(words2))
        union = len(words1.union(words2))
        
        if union == 0:
            return 0.0
        
        return intersection / union
    
    def _create_features(self, projects_df: pd.DataFrame) -> pd.DataFrame:
        logger.info("Creating feature matrix")
        
        # Pilih dan standarisasi fitur numerik
        numeric_features = [
            'market_cap', 'total_volume', 'current_price',
            'price_change_percentage_24h', 'price_change_percentage_7d_in_currency',
            'popularity_score', 'trend_score', 'developer_activity_score', 'social_engagement_score'
        ]
        
        # Pilih hanya kolom yang ada
        available_numeric = [col for col in numeric_features if col in projects_df.columns]
        
        # Buat salinan fitur yang ada
        features_df = projects_df[['id'] + available_numeric].copy()
        
        # One-hot encode kategori
        if 'primary_category' in projects_df.columns:
            category_dummies = pd.get_dummies(projects_df['primary_category'], prefix='category')
            features_df = pd.concat([features_df, category_dummies], axis=1)
        
        # One-hot encode chain
        if 'chain' in projects_df.columns:
            chain_dummies = pd.get_dummies(projects_df['chain'], prefix='chain')
            features_df = pd.concat([features_df, chain_dummies], axis=1)
        
        # Normalisasi fitur numerik
        numeric_columns = features_df.columns.difference(['id'])
        
        # Gunakan Min-Max scaling untuk normalisasi (0-1)
        for col in numeric_columns:
            if col in available_numeric:  # Normalisasi hanya fitur numerik
                min_val = features_df[col].min()
                max_val = features_df[col].max()
                
                if max_val > min_val:
                    features_df[col] = (features_df[col] - min_val) / (max_val - min_val)
                else:
                    features_df[col] = 0
        
        return features_df
    
    def _create_synthetic_interactions(self, projects_df: pd.DataFrame, n_users: int = 500) -> pd.DataFrame:
        """
        Membuat interaksi sintetis dengan pola yang lebih realistis dan lebih acak
        """
        logger.info(f"Creating synthetic interactions for {n_users} users")
        
        # Create global RNG instance with fixed seed for reproducibility
        base_seed = EVAL_RANDOM_SEED
        global_rng = np.random.RandomState(base_seed)
        
        # Create total time range for all interactions (now - max_days_ago to now)
        now = datetime.now()
        global_max_days_ago = 90  # Use a larger timeframe for better distribution
        global_start_date = now - timedelta(days=global_max_days_ago)
        
        # Define personas and user types as before
        personas = list(USER_PERSONAS.keys())
        
        # Generate dynamic persona weights that change for each batch of users
        num_personas = len(personas)
        persona_distributions = []
        
        # Create multiple persona distributions for different user batches
        num_batches = 5  # Divide users into batches with different distributions
        for i in range(num_batches):
            batch_seed = global_rng.randint(1000, 10000)
            batch_rng = np.random.RandomState(batch_seed)
            
            weights = batch_rng.random(size=num_personas)
            weights = weights / weights.sum()  # Normalize
            
            # Add some skew to make certain personas more common in each batch
            skew_factor = batch_rng.randint(0, num_personas)
            weights[skew_factor] *= batch_rng.uniform(1.5, 2.5)
            weights = weights / weights.sum()  # Re-normalize
            
            persona_distributions.append(weights)
        
        logger.info(f"Created {len(persona_distributions)} different persona distributions for user batches")
        
        # Extract all categories and their frequencies for more realistic exploration
        all_categories = projects_df['primary_category'].dropna().tolist()
        category_freq = pd.Series(all_categories).value_counts(normalize=True).to_dict()
        unique_categories = list(category_freq.keys())
        
        # Define user activity profiles - UPDATED WITH REALISTIC INTERACTION COUNTS
        activity_profiles = {
            'very_casual': {
                'interaction_count_range': (3, 8),  # REDUCED FROM ORIGINAL
                'session_patterns': ['single_session'],
                'exploration_rate': (0.15, 0.25),  # More exploration
                'popularity_bias': (0.5, 0.7),  # Less popularity bias
                'category_focus': (0.5, 0.7),  # Less category focus
                'weight_boost': (0.8, 1.2),
                'probs': 0.25  # Increased probability
            },
            'casual': {
                'interaction_count_range': (7, 15),  # REDUCED FROM ORIGINAL
                'session_patterns': ['single_session', 'few_sessions'],
                'exploration_rate': (0.2, 0.35),
                'popularity_bias': (0.4, 0.6),
                'category_focus': (0.5, 0.7),
                'weight_boost': (0.9, 1.3),
                'probs': 0.40  # Increased probability
            },
            'regular': {
                'interaction_count_range': (12, 25),  # REDUCED FROM ORIGINAL
                'session_patterns': ['few_sessions', 'regular_sessions'],
                'exploration_rate': (0.25, 0.4),
                'popularity_bias': (0.3, 0.6),
                'category_focus': (0.4, 0.6),
                'weight_boost': (1.0, 1.5),
                'probs': 0.25  # Increased probability
            },
            'active': {
                'interaction_count_range': (20, 35),  # REDUCED FROM ORIGINAL
                'session_patterns': ['regular_sessions', 'random_spread'],
                'exploration_rate': (0.3, 0.5),
                'popularity_bias': (0.3, 0.5),
                'category_focus': (0.4, 0.6),
                'weight_boost': (1.2, 1.8),
                'probs': 0.08  # Reduced probability
            },
            'power_user': {
                'interaction_count_range': (30, 50),  # REDUCED FROM ORIGINAL
                'session_patterns': ['random_spread', 'regular_sessions'],
                'exploration_rate': (0.4, 0.6),
                'popularity_bias': (0.2, 0.4),
                'category_focus': (0.3, 0.5),
                'weight_boost': (1.5, 2.2),
                'probs': 0.02  # Very rare
            }
        }
        
        # Calculate activity profile probabilities
        activity_types = list(activity_profiles.keys())
        activity_probs = [activity_profiles[t]['probs'] for t in activity_types]
        
        # Store all interactions before creating DataFrame
        all_interactions = []
        
        # Generate users with much greater randomness and significantly different patterns
        for user_idx in range(1, n_users + 1):
            # Create user-specific RNG with unique seed
            user_seed = global_rng.randint(10000, 1000000) + user_idx
            user_rng = np.random.RandomState(user_seed)
            
            # Determine which batch this user belongs to
            batch_idx = user_idx % num_batches
            
            # Assign persona with more varied weights per batch 
            user_persona = user_rng.choice(personas, p=persona_distributions[batch_idx])
            
            # Error handling for missing persona
            if user_persona not in USER_PERSONAS:
                logger.warning(f"Persona {user_persona} not found in USER_PERSONAS, using default")
                user_persona = personas[0]
                
            persona_data = USER_PERSONAS[user_persona]
            
            # Select user activity profile with probability weighting
            activity_type = user_rng.choice(activity_types, p=activity_probs)
            activity_profile = activity_profiles[activity_type]
            
            # Generate interaction count with extreme variability
            min_count, max_count = activity_profile['interaction_count_range']
            
            # Use log-normal distribution for more realistic tails
            mu = np.log((min_count + max_count) / 2)
            sigma = (np.log(max_count) - np.log(min_count)) / 4
            n_interactions = int(user_rng.lognormal(mu, sigma))
            n_interactions = max(min_count, min(max_count, n_interactions))
            
            # Ensure minimum 3 interactions for evaluation purposes
            n_interactions = max(3, n_interactions)
            
            # More varied preferences based on persona with added randomness
            preferred_categories = persona_data['categories']
            
            # Calculate significantly more varied weights with dynamic noise level
            raw_weights = np.array(persona_data['weights'])
            noise_magnitude = user_rng.uniform(0.2, 0.5)  # Higher noise range
            noise = user_rng.normal(0, noise_magnitude, len(raw_weights))
            
            # Add chance for completely inverted preferences (extremely different user pattern)
            if user_rng.random() < 0.08:  # 8% chance
                raw_weights = 1 - raw_weights  # Invert preferences
                logger.debug(f"User {user_idx} has inverted category preferences")
            
            # Apply noise to create highly variable preferences
            category_weights = raw_weights + noise
            
            # Ensure weights are positive and normalized
            category_weights = np.clip(category_weights, 0.05, 0.95)
            category_weights = category_weights / category_weights.sum()
            
            # Add secondary categories preferences with varying weights
            available_categories = [c for c in unique_categories if c not in preferred_categories]
            if available_categories:
                num_extra = min(user_rng.randint(2, 5), len(available_categories))
                extra_categories = user_rng.choice(available_categories, size=num_extra, replace=False)
                
                # Create new arrays with extra capacity
                new_preferred = preferred_categories.copy()
                new_weights = category_weights.copy()
                
                # Add extra categories and weights
                for extra_cat in extra_categories:
                    new_preferred.append(extra_cat)
                    new_weights = np.append(new_weights, user_rng.uniform(0.1, 0.4))  # Higher weights for better diversity
                
                # Update the original arrays
                preferred_categories = new_preferred
                category_weights = new_weights / new_weights.sum()  # Re-normalize
            
            # More variable exploration decay patterns
            explorer_type = user_rng.choice([
                'consistent',      # Maintains exploration level
                'quick_decay',     # Rapidly loses interest in exploration
                'slow_decay',      # Gradually loses interest
                'fluctuating',     # Interest comes and goes
                'increasing'       # Becomes more exploratory over time
            ])
            
            # Select exploration parameters from profile ranges
            min_explore, max_explore = activity_profile['exploration_rate']
            base_exploration_prob = user_rng.uniform(min_explore, max_explore)
            exploration_probability = base_exploration_prob
            exploration_decay = user_rng.uniform(0.7, 0.95)  # Base rate
            
            # Popularity bias - Extreme variation with lower overall bias for crypto
            min_pop, max_pop = activity_profile['popularity_bias']
            popularity_bias = user_rng.beta(5 * min_pop, 5 * (1-max_pop))
            
            # Time distribution for interactions
            session_patterns = activity_profile['session_patterns']
            session_clustering = user_rng.choice(session_patterns)
            
            # Add special patterns for more variation
            if user_rng.random() < 0.15:  # 15% chance of special pattern
                special_patterns = [
                    'binge_and_break',      # Activity in bursts with long breaks
                    'weekend_only',         # Active only on specific days
                    'gradually_increasing', # Starts slow, becomes more active
                    'gradually_decreasing'  # Starts active, loses interest
                ]
                session_clustering = user_rng.choice(special_patterns)
            
            # Create user-specific time window within the global time range
            user_start_offset = user_rng.randint(0, global_max_days_ago // 2)  # Random start within first half of global range
            user_time_range = global_max_days_ago - user_start_offset
            user_max_days_ago = min(user_time_range, 30 + user_rng.randint(0, 60))  # User's active period
            
            # Generate timestamps with improved distribution
            timestamps = self._generate_user_timestamps(n_interactions, user_max_days_ago, user_start_offset, user_rng)
            
            # Track selected projects
            selected_projects = []
            
            # Different interest patterns
            interest_pattern = user_rng.choice([
                'stable',           # Consistent interests
                'explorer',         # High variety, exploring many categories
                'deep_diver',       # Focuses intensely on a few categories
                'trend_follower',   # Prefers trending projects
                'contrarian',       # Avoids popular projects
                'fad_chaser'        # Interest shifts dramatically over time
            ])
            
            # For fad_chaser, generate category shift points
            category_shifts = []
            phase_categories = {}
            if interest_pattern == 'fad_chaser':
                # Divide interactions into 2-4 phases with different category focus
                num_phases = user_rng.randint(2, 5)
                shift_points = sorted(user_rng.choice(
                    range(1, n_interactions), 
                    size=min(num_phases-1, n_interactions-1),
                    replace=False
                ))
                category_shifts = shift_points
                
                # For each phase, select a different primary category
                for i in range(num_phases):
                    phase_categories[i] = user_rng.choice(preferred_categories)
            
            # Initialize variables for tracking category history
            category_history = []  # Keep track of recently selected categories
            category_counts = {}   # Track overall category counts
            
            # Enhanced interaction type probabilities - Make more unpredictable for crypto
            # Base probabilities vary widely between users
            view_base = user_rng.uniform(0.35, 0.6)  # More views
            favorite_base = user_rng.uniform(0.15, 0.35)  
            portfolio_base = user_rng.uniform(0.1, 0.25)  # Less portfolio adds
            research_base = user_rng.uniform(0.05, 0.2)
            
            # Normalize to sum to 1
            total = view_base + favorite_base + portfolio_base + research_base
            view_base /= total
            favorite_base /= total
            portfolio_base /= total
            research_base /= total
            
            # For each interaction, select project with HIGHLY variable behavior
            for interaction_idx in range(n_interactions):
                # Update exploration probability based on explorer type
                if explorer_type == 'quick_decay':
                    exploration_probability *= (exploration_decay ** 1.5)  # Faster decay
                elif explorer_type == 'slow_decay':
                    exploration_probability *= (exploration_decay ** 0.5)  # Slower decay
                elif explorer_type == 'fluctuating':
                    # Random fluctuations
                    if user_rng.random() < 0.3:
                        exploration_probability = user_rng.uniform(0.1, 0.6)
                elif explorer_type == 'increasing':
                    # Gradually increase exploration
                    exploration_probability = min(0.8, base_exploration_prob + 
                                                (interaction_idx / n_interactions) * 0.4)
                
                # Determine if this interaction will be exploratory
                is_exploratory = user_rng.random() < exploration_probability
                
                # Time-dependent exploration
                if not is_exploratory and interaction_idx < n_interactions // 3:
                    # More likely to explore early
                    is_exploratory = user_rng.random() < 0.3
                
                # Select category based on exploration mode
                if is_exploratory:
                    # Exploration mode - venture beyond normal preferences
                    explore_type = user_rng.choice([
                        'random',           # Completely random category
                        'adjacent',         # Related to interests
                        'trending',         # Focus on trending
                        'contrarian',       # Focus on non-popular
                        'novelty'           # Focus on categories not yet explored
                    ])
                    
                    if explore_type == 'random':
                        # Completely random category
                        selected_category = user_rng.choice(unique_categories)
                        
                    elif explore_type == 'adjacent':
                        # Find categories related to current interests
                        if category_history and user_rng.random() < 0.7:
                            # Base on recent history
                            recent_cat = user_rng.choice(category_history[:5]) if len(category_history) >= 5 else category_history[-1]
                            
                            # Use simplified category similarity for selection
                            selected_category = user_rng.choice(unique_categories)
                        else:
                            # Base on overall preferences
                            selected_category = user_rng.choice(preferred_categories)
                            
                    elif explore_type == 'trending' or explore_type == 'contrarian':
                        # Just use random category for simplicity in synthetic data
                        selected_category = user_rng.choice(unique_categories)
                        
                    else:  # novelty
                        # Focus on categories not yet explored
                        explored_cats = set(category_history)
                        unexplored = [cat for cat in unique_categories if cat not in explored_cats]
                        
                        if unexplored:
                            selected_category = user_rng.choice(unexplored)
                        else:
                            # All categories explored, choose random
                            selected_category = user_rng.choice(unique_categories)
                else:
                    # Normal selection based on preferences
                    if interest_pattern == 'deep_diver' and category_history:
                        last_cat = category_history[-1]
                        if last_cat in preferred_categories and user_rng.random() < 0.7:
                            selected_category = last_cat
                        else:
                            # Select from preferences with probability weighting
                            selected_category = user_rng.choice(preferred_categories, p=category_weights)
                    else:
                        # Standard selection with weights
                        selected_category = user_rng.choice(preferred_categories, p=category_weights)
                
                # Update category history
                category_history.insert(0, selected_category)
                if len(category_history) > 10:  # Keep only most recent 10
                    category_history.pop()
                    
                # Update category counts
                category_counts[selected_category] = category_counts.get(selected_category, 0) + 1
                
                # Filter projects by selected category with high variability in selection
                category_projects = projects_df[projects_df['primary_category'] == selected_category]
                
                # If too few projects found, expand search intelligently
                if len(category_projects) < 3:
                    # Add some popular projects as fallback
                    popular_projects = projects_df.sort_values('popularity_score', ascending=False).head(20)
                    category_projects = pd.concat([category_projects, popular_projects])
                    category_projects = category_projects.drop_duplicates(subset=['id'])
                
                # Project selection with even greater randomness for crypto domain
                # Select a project with high randomness
                if len(category_projects) > 0:
                    if user_rng.random() < 0.8:  # 80% standard selection
                        project_row = category_projects.sample(1, random_state=user_rng.randint(0, 10000)).iloc[0]
                    else:  # 20% completely random selection
                        project_row = projects_df.sample(1, random_state=user_rng.randint(0, 10000)).iloc[0]
                    
                    project_id = project_row['id']
                    selected_projects.append(project_id)
                else:
                    # Fallback to random project if category is empty
                    if len(projects_df) > 0:
                        project_row = projects_df.sample(1, random_state=user_rng.randint(0, 10000)).iloc[0]
                        project_id = project_row['id']
                        selected_projects.append(project_id)
                    else:
                        continue  # Skip this interaction if no projects
                
                # Generate interaction type with much more randomness
                # Adjust base probabilities randomly for each interaction
                random_factor = user_rng.uniform(0.8, 1.2)
                adjusted_view = min(0.8, view_base * random_factor)
                adjusted_favorite = min(0.5, favorite_base * random_factor)
                adjusted_portfolio = min(0.4, portfolio_base * random_factor)
                adjusted_research = min(0.3, research_base * random_factor)
                
                # Normalize
                total = adjusted_view + adjusted_favorite + adjusted_portfolio + adjusted_research
                probs = {
                    'view': adjusted_view / total,
                    'favorite': adjusted_favorite / total,
                    'portfolio_add': adjusted_portfolio / total,
                    'research': adjusted_research / total
                }
                
                # Select interaction type
                interaction_types = list(probs.keys())
                interaction_probs = list(probs.values())
                interaction_type = user_rng.choice(interaction_types, p=interaction_probs)
                
                # Determine interaction weight with more realistic distribution
                # Views have lower weight, portfolio adds have higher weight
                if interaction_type == 'view':
                    weight = max(1, int(user_rng.exponential(1.0)))  # Mostly low values
                    weight = min(weight, 5)  # Cap at 5
                elif interaction_type == 'favorite':
                    weight = max(1, int(user_rng.normal(2.0, 1.0)))  # Centered around 2
                    weight = min(weight, 6)  # Cap at 6
                elif interaction_type == 'portfolio_add':
                    weight = max(1, int(user_rng.normal(3.0, 1.5)))  # Centered around 3
                    weight = min(weight, 8)  # Cap at 8
                else:  # research
                    weight = max(1, int(user_rng.normal(2.5, 1.2)))  # Centered around 2.5
                    weight = min(weight, 7)  # Cap at 7
                
                # Get timestamp for this interaction
                if interaction_idx < len(timestamps):
                    interaction_time = timestamps[interaction_idx]
                else:
                    # Fallback if not enough timestamps
                    days_ago = int(user_rng.randint(0, user_max_days_ago))
                    random_seconds = int(user_rng.randint(0, 86400))
                    interaction_time = now - timedelta(days=int(days_ago+user_start_offset), seconds=int(random_seconds))
                
                # Add to all interactions
                all_interactions.append({
                    'user_id': f"user_{user_idx}",
                    'project_id': project_id,
                    'interaction_type': interaction_type,
                    'weight': weight,
                    'timestamp': interaction_time.strftime('%Y-%m-%dT%H:%M:%S.%f')
                })
        
        # Create DataFrame
        interactions_df = pd.DataFrame(all_interactions)
        
        if not interactions_df.empty:
            # Pastikan format timestamp konsisten
            try:
                # Pertama coba dengan format default
                interactions_df['timestamp'] = pd.to_datetime(interactions_df['timestamp'])
            except ValueError:
                # Jika gagal, gunakan format yang lebih fleksibel
                interactions_df['timestamp'] = pd.to_datetime(interactions_df['timestamp'], format='ISO8601')
            
            # Sort ALL interactions by timestamp only, not by user
            interactions_df = interactions_df.sort_values('timestamp')
            
            # Format yang konsisten dengan mikrodetik untuk mencegah kesalahan di masa depan
            interactions_df['timestamp'] = interactions_df['timestamp'].dt.strftime('%Y-%m-%dT%H:%M:%S.%f')
        
        # Verifikasi statistik interaksi
        user_interaction_counts = interactions_df.groupby('user_id').size()
        min_interactions = user_interaction_counts.min()
        
        logger.info(f"Minimum interactions per user: {min_interactions}")
        logger.info(f"Maximum interactions per user: {user_interaction_counts.max()}")
        logger.info(f"Average interactions per user: {user_interaction_counts.mean():.2f}")
        logger.info(f"Median interactions per user: {user_interaction_counts.median():.2f}")
        
        return interactions_df
    
    def _generate_user_timestamps(self, n_interactions: int, max_days_ago: int, start_offset: int, user_rng) -> List[datetime]:
        now = datetime.now()
        timestamps = []
        
        # Determine user pattern type
        user_pattern = user_rng.choice([
            'casual',       # Infrequent, irregular usage
            'regular',      # Regular but not frequent
            'active',       # Active, almost daily
            'bursty',       # Active in short periods
            'declining',    # Active initially, declining
            'increasing',   # Increasingly active over time
            'weekend',      # Active on weekends
            'workday'       # Active on workdays
        ], p=[0.25, 0.25, 0.15, 0.1, 0.1, 0.05, 0.05, 0.05])
        
        # How many days in the time range
        active_days = min(max_days_ago, max(7, int(user_rng.gamma(shape=3.0, scale=max_days_ago/10))))
        
        # Which days are active, based on pattern
        if user_pattern == 'casual':
            # 10-20% active days, randomly distributed
            active_day_count = int(active_days * user_rng.uniform(0.1, 0.2))
            active_day_indices = sorted(user_rng.choice(range(active_days), size=min(active_day_count, active_days), replace=False))
        elif user_pattern == 'regular':
            # 20-40% active days, more evenly distributed
            active_day_count = int(active_days * user_rng.uniform(0.2, 0.4))
            active_day_indices = sorted(user_rng.choice(range(active_days), size=min(active_day_count, active_days), replace=False))
        elif user_pattern == 'active':
            # 40-70% active days
            active_day_count = int(active_days * user_rng.uniform(0.4, 0.7))
            active_day_indices = sorted(user_rng.choice(range(active_days), size=min(active_day_count, active_days), replace=False))
        elif user_pattern == 'bursty':
            # Active in 1-3 short periods
            burst_count = user_rng.randint(1, 4)
            active_day_indices = []
            for _ in range(burst_count):
                burst_center = user_rng.randint(0, active_days)
                burst_length = user_rng.randint(1, 5)
                for day in range(max(0, burst_center - burst_length), min(active_days, burst_center + burst_length)):
                    active_day_indices.append(day)
            active_day_indices = sorted(set(active_day_indices))
        elif user_pattern == 'declining':
            # More active early, declining
            active_day_count = int(active_days * user_rng.uniform(0.3, 0.5))
            weights = np.linspace(1.0, 0.1, active_days)
            active_day_indices = sorted(user_rng.choice(
                range(active_days), 
                size=min(active_day_count, active_days), 
                replace=False, 
                p=weights/weights.sum()
            ))
        elif user_pattern == 'increasing':
            # More active later
            active_day_count = int(active_days * user_rng.uniform(0.3, 0.5))
            weights = np.linspace(0.1, 1.0, active_days)
            active_day_indices = sorted(user_rng.choice(
                range(active_days), 
                size=min(active_day_count, active_days), 
                replace=False, 
                p=weights/weights.sum()
            ))
        elif user_pattern == 'weekend':
            # Active on weekends
            active_day_indices = []
            for day in range(active_days):
                # Simulate weekend (assume day 0 is today, and today is arbitrary)
                is_weekend = (now - timedelta(days=int(day+start_offset))).weekday() >= 5
                if is_weekend or user_rng.random() < 0.15:  # 15% chance of activity on weekdays
                    active_day_indices.append(day)
        else:  # workday
            # Active on workdays
            active_day_indices = []
            for day in range(active_days):
                # Simulate workday
                is_workday = (now - timedelta(days=int(day+start_offset))).weekday() < 5
                if is_workday or user_rng.random() < 0.15:  # 15% chance of activity on weekends
                    active_day_indices.append(day)

        # Handle case with no active days
        if not active_day_indices:
            # Fallback to some random days
            active_day_count = max(1, int(active_days * 0.1))
            active_day_indices = sorted(user_rng.choice(range(active_days), size=min(active_day_count, active_days), replace=False))
        
        # Distribute interactions to active days with realistic variation
        interactions_per_day = [0] * active_days
        remaining = n_interactions
        
        # Allocate minimum 1 interaction per active day
        for day in active_day_indices:
            if day < len(interactions_per_day):  # Safety check
                interactions_per_day[day] = 1
                remaining -= 1
                if remaining <= 0:
                    break
        
        # Allocate remaining interactions with natural distribution
        if remaining > 0 and active_day_indices:
            # Variation in intensity per day
            activity_intensity = user_rng.choice(['low', 'medium', 'high', 'mixed'])
            
            if activity_intensity == 'low':
                # 1-3 interactions per active day
                max_per_day = 3
            elif activity_intensity == 'medium':
                # 1-7 interactions per active day
                max_per_day = 7
            elif activity_intensity == 'high':
                # 1-12 interactions per active day
                max_per_day = 12
            else:  # mixed
                # High variation, some days very active
                max_per_day = 20
            
            # Distribute with bias toward certain days
            while remaining > 0 and sum(1 for d in interactions_per_day if d < max_per_day) > 0:
                # Select active day randomly, prioritize those with existing activity
                weights = [
                    (d + 1) if i in active_day_indices and d < max_per_day else 0 
                    for i, d in enumerate(interactions_per_day)
                ]
                
                if sum(weights) == 0:
                    break
                    
                weights_array = np.array(weights)
                day_idx = user_rng.choice(range(active_days), p=weights_array/sum(weights_array))
                interactions_per_day[day_idx] += 1
                remaining -= 1
        
        # Convert to timestamps
        for day, count in enumerate(interactions_per_day):
            if count <= 0:
                continue
                
            # How are interactions distributed within the day?
            day_pattern = user_rng.choice(['morning', 'evening', 'random', 'work_hours'])
            
            for _ in range(count):
                if day_pattern == 'morning':
                    hour = user_rng.randint(6, 12)
                elif day_pattern == 'evening':
                    hour = user_rng.randint(17, 23)
                elif day_pattern == 'work_hours':
                    hour = user_rng.randint(9, 18)
                else:  # random
                    hour = user_rng.randint(0, 24)
                    
                minute = user_rng.randint(0, 60)
                second = user_rng.randint(0, 60)
                
                # Apply start_offset to better distribute users in time
                total_days_ago = day + start_offset
                
                timestamp = now - timedelta(
                    days=int(total_days_ago),
                    hours=int(hour),
                    minutes=int(minute),
                    seconds=int(second)
                )
                timestamps.append(timestamp)
        
        # If there are remaining interactions to allocate, add them randomly
        while remaining > 0:
            day = user_rng.randint(0, active_days)
            hour = user_rng.randint(0, 24)
            minute = user_rng.randint(0, 60)
            second = user_rng.randint(0, 60)
            
            # Apply start_offset to better distribute users in time
            total_days_ago = day + start_offset
            
            timestamp = now - timedelta(
                days=int(total_days_ago),
                hours=int(hour),
                minutes=int(minute),
                seconds=int(second)
            )
            timestamps.append(timestamp)
            remaining -= 1
        
        # Sort timestamps in chronological order (oldest to newest)
        timestamps.sort()
        return timestamps

    def clean_json_string(self, json_str):
        import re
        
        if not isinstance(json_str, str):
            return json_str
        
        # Step 1: Fix double quotes at the beginning and end of the entire string
        if json_str.startswith('""') and json_str.endswith('""'):
            json_str = json_str[1:-1]
        
        # Step 2: Fix patterns like ""key"": ""value"" to "key": "value"
        # This handles the issue with platforms and other objects
        pattern = r'""([^"]+)""\s*:\s*""([^"]+)""'
        json_str = re.sub(pattern, r'"\1": "\2"', json_str)
        
        # Step 3: Fix patterns like [""item1"", ""item2""] to ["item1", "item2"]
        # This handles the issue with categories and other arrays
        pattern = r'\[""([^"]+)"",\s*""([^"]+)""'
        while re.search(pattern, json_str):
            json_str = re.sub(pattern, r'["\1", "\2"', json_str)
        
        # Fix the closing bracket too
        pattern = r'""([^"]+)""\]'
        json_str = re.sub(pattern, r'"\1"]', json_str)
        
        # Step 4: Fix any remaining ""value"" patterns
        pattern = r'""([^"]+)""'
        json_str = re.sub(pattern, r'"\1"', json_str)
        
        # Step 5: Fix structure quirks (e.g. quoted braces)
        json_str = json_str.replace('"{', '{').replace('}"', '}')
        json_str = json_str.replace('"[', '[').replace(']"', ']')
        
        return json_str
    
    def _save_processed_data(self, projects_df: pd.DataFrame, interactions_df: pd.DataFrame, features_df: pd.DataFrame) -> None:
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Paths with timestamp
        projects_path = os.path.join(PROCESSED_DIR, f"projects_{timestamp}.csv")
        interactions_path = os.path.join(PROCESSED_DIR, f"interactions_{timestamp}.csv")
        features_path = os.path.join(PROCESSED_DIR, f"features_{timestamp}.csv")
        
        # Standard paths
        projects_std_path = os.path.join(PROCESSED_DIR, "projects.csv")
        interactions_std_path = os.path.join(PROCESSED_DIR, "interactions.csv")
        features_std_path = os.path.join(PROCESSED_DIR, "features.csv")
        
        # Create a copy for CSV export
        projects_df_csv = projects_df.copy()

        # Hapus kolom redundan dan tidak diperlukan
        columns_to_remove = []

        # Hapus kolom deskripsi yang tidak diperlukan
        for col in ['description_length_raw', 'description_word_count', 'description_avg_word_length']:
            if col in projects_df_csv.columns:
                columns_to_remove.append(col)
                
        # Hapus kolom sosial yang tidak diperlukan
        for col in ['reddit_subscribers', 'discord_members', 'facebook_likes']:
            if col in projects_df_csv.columns:
                columns_to_remove.append(col)
        
        # Hapus kolom sekaligus
        if columns_to_remove:
            logger.info(f"Removing unnecessary columns: {columns_to_remove}")
            projects_df_csv = projects_df_csv.drop(columns=columns_to_remove)
        
        # Properly handle 'platforms' column
        if 'platforms' in projects_df_csv.columns:
            def process_platforms(x):
                if isinstance(x, dict):
                    # Use json.dumps with ensure_ascii=False to avoid escaping Unicode
                    return json.dumps(x, ensure_ascii=False)
                elif x is None or (isinstance(x, float) and np.isnan(x)):
                    return json.dumps({}, ensure_ascii=False)
                else:
                    try:
                        # Try to parse the string if it's already a JSON string
                        if isinstance(x, str):
                            # Clean the string first
                            cleaned = self.clean_json_string(x)
                            return json.dumps(json.loads(cleaned), ensure_ascii=False)
                    except:
                        pass
                    # Default to empty dict for unknown values
                    return json.dumps({}, ensure_ascii=False)
            
            projects_df_csv['platforms'] = projects_df_csv['platforms'].apply(process_platforms)
        
        # Properly handle 'categories' column
        if 'categories' in projects_df_csv.columns:
            def process_categories(x):
                if isinstance(x, list):
                    return json.dumps(x, ensure_ascii=False)
                elif x is None or (isinstance(x, float) and np.isnan(x)):
                    return json.dumps([], ensure_ascii=False)
                else:
                    try:
                        # Try to parse the string if it's already a JSON string
                        if isinstance(x, str):
                            # Clean the string first
                            cleaned = self.clean_json_string(x)
                            return json.dumps(json.loads(cleaned), ensure_ascii=False)
                    except:
                        pass
                    # Default to empty list for unknown values
                    return json.dumps([], ensure_ascii=False)
            
            projects_df_csv['categories'] = projects_df_csv['categories'].apply(process_categories)
        
        # Ensure all required fields are not null
        for field in ['name', 'symbol', 'primary_category', 'chain']:
            if field in projects_df_csv.columns:
                projects_df_csv[field] = projects_df_csv[field].fillna('unknown')
        
        # Ensure numeric fields are not null
        for field in ['market_cap', 'current_price', 'total_volume', 'popularity_score', 'trend_score']:
            if field in projects_df_csv.columns:
                projects_df_csv[field] = projects_df_csv[field].fillna(0)
        
        # Save with timestamp
        projects_df_csv.to_csv(projects_path, index=False, quoting=1)  # Use quoting=1 to quote strings only
        interactions_df.to_csv(interactions_path, index=False)
        features_df.to_csv(features_path, index=False)
        
        # Save standard paths
        projects_df_csv.to_csv(projects_std_path, index=False, quoting=1)
        interactions_df.to_csv(interactions_std_path, index=False)
        features_df.to_csv(features_std_path, index=False)
        
        logger.info(f"Saved processed data to {PROCESSED_DIR}")
        logger.info(f"Projects: {len(projects_df_csv)} rows with {len(projects_df_csv.columns)} columns")
        logger.info(f"Interactions: {len(interactions_df)} rows")
        logger.info(f"Features: {features_df.shape}")

    def load_processed_data(self) -> Tuple[pd.DataFrame, pd.DataFrame, pd.DataFrame]:
        # Standard paths
        projects_path = os.path.join(PROCESSED_DIR, "projects.csv")
        interactions_path = os.path.join(PROCESSED_DIR, "interactions.csv")
        features_path = os.path.join(PROCESSED_DIR, "features.csv")
        
        # Check if files exist
        if not all(os.path.exists(path) for path in [projects_path, interactions_path, features_path]):
            logger.warning("Processed data files not found, processing raw data...")
            return self.process_data()
        
        try:
            # Load data
            projects_df = pd.read_csv(projects_path)
            interactions_df = pd.read_csv(interactions_path)
            features_df = pd.read_csv(features_path)
            
            # Convert string representations back to Python objects
            if 'platforms' in projects_df.columns:
                def process_platforms(x):
                    if isinstance(x, str):
                        try:
                            # Clean the string first then parse
                            cleaned = self.clean_json_string(x)
                            return json.loads(cleaned)
                        except json.JSONDecodeError as e:
                            logger.warning(f"Error parsing platforms JSON: {e}. Original value: {x[:100]}")
                            return {}
                    elif pd.isna(x):
                        return {}
                    return x
                
                projects_df['platforms'] = projects_df['platforms'].apply(process_platforms)
                    
            if 'categories' in projects_df.columns:
                def process_categories(x):
                    if isinstance(x, str):
                        try:
                            # Clean the string first then parse
                            cleaned = self.clean_json_string(x)
                            return json.loads(cleaned)
                        except json.JSONDecodeError as e:
                            logger.warning(f"Error parsing categories JSON: {e}. Original value: {x[:100]}")
                            return []
                    elif pd.isna(x):
                        return []
                    return x
                
                projects_df['categories'] = projects_df['categories'].apply(process_categories)
            
            logger.info(f"Loaded processed data: {len(projects_df)} projects, {len(interactions_df)} interactions")
            return projects_df, interactions_df, features_df
            
        except Exception as e:
            logger.error(f"Error loading processed data: {e}")
            import traceback
            logger.error(traceback.format_exc())
            logger.info("Attempting to process raw data instead...")
            return self.process_data()


if __name__ == "__main__":
    # Test mode
    processor = DataProcessor()
    
    # Process data
    try:
        projects_df, interactions_df, features_df = processor.process_data(n_users=100)
        
        print(f"Processed {len(projects_df)} projects")
        print(f"Generated {len(interactions_df)} interactions")
        print(f"Created feature matrix with shape {features_df.shape}")
        
        # Print top projects by popularity
        print("\nTop 5 projects by popularity:")
        top_popular = projects_df.sort_values('popularity_score', ascending=False).head(5)
        for _, row in top_popular.iterrows():
            print(f"{row['name']} ({row['symbol']}): Score={row['popularity_score']:.2f}, Category={row['primary_category']}")
        
        # Print top trending projects
        print("\nTop 5 trending projects:")
        top_trending = projects_df.sort_values('trend_score', ascending=False).head(5)
        for _, row in top_trending.iterrows():
            print(f"{row['name']} ({row['symbol']}): Score={row['trend_score']:.2f}, Category={row['primary_category']}")
        
        # Print category distribution
        print("\nCategory distribution:")
        category_counts = projects_df['primary_category'].value_counts()
        for category, count in category_counts.items():
            print(f"{category}: {count} projects")
        
    except ValueError as e:
        print(f"Error: {e}")
        print("Make sure to run data collection first!")