import os
import sys
from typing import List, Dict, Any, Optional
from datetime import datetime
from fastapi import APIRouter, HTTPException, Body
from pydantic import BaseModel, Field
import logging

# Add the parent directory to sys.path to import our modules
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

try:
    from src.data.multi_api_collector import MultiAPICollector, TransactionData
except ImportError as e:
    logging.error(f"Error importing MultiAPICollector: {e}")
    # Fallback untuk development
    MultiAPICollector = None
    TransactionData = None

# Setup router
router = APIRouter(
    prefix="/transactions",
    tags=["transactions"],
    responses={404: {"description": "Not found"}},
)

# Setup logging
logger = logging.getLogger(__name__)

# Pydantic models
class TransactionSyncRequest(BaseModel):
    user_id: str = Field(..., description="User ID to sync transactions for")
    wallet_addresses: List[str] = Field(..., description="List of wallet addresses to sync")
    chains: List[str] = Field(default=['eth', 'bsc', 'polygon'], description="Blockchain chains to sync")
    limit: int = Field(default=100, ge=1, le=1000, description="Maximum number of transactions per chain")
    contract_addresses: Optional[List[str]] = Field(default=None, description="Specific token contract addresses to filter")

class TransactionResponse(BaseModel):
    tx_hash: str
    from_address: str
    to_address: str
    value: float
    token_symbol: str
    token_address: str
    block_number: int
    timestamp: str  # ISO format
    gas_used: int
    gas_price: float
    transaction_type: str
    chain: str
    project_id: Optional[str] = None
    # Additional fields for Laravel compatibility
    source: str = "api_sync"
    is_verified: bool = True
    raw_data: Dict[str, Any] = {}

class TransactionSyncResponse(BaseModel):
    user_id: str
    total_transactions: int
    transactions: List[TransactionResponse]
    chains_synced: List[str]
    sync_timestamp: str
    errors: List[str] = []
    api_sources_used: List[str] = []

class PriceUpdateRequest(BaseModel):
    symbols: List[str] = Field(..., description="List of token symbols to get prices for")
    source: str = Field(default="multiple", description="Price source to use")

class PriceUpdateResponse(BaseModel):
    prices: Dict[str, float]
    timestamp: str
    source: str
    symbols_found: List[str]
    symbols_missing: List[str]

# Initialize the collector
collector = None

def get_collector():
    global collector
    if collector is None:
        collector = MultiAPICollector()
    return collector

@router.post("/sync", response_model=TransactionSyncResponse)
async def sync_user_transactions(request: TransactionSyncRequest):
    """
    Sync user transactions from multiple blockchain APIs
    """
    logger.info(f"Starting transaction sync for user {request.user_id}")
    
    try:
        api_collector = get_collector()
        all_transactions = []
        chains_synced = []
        errors = []
        api_sources_used = []
        
        # Sync transactions for each wallet address
        for wallet_address in request.wallet_addresses:
            logger.info(f"Syncing transactions for wallet {wallet_address}")
            
            try:
                # Get transactions from all specified chains
                wallet_transactions = api_collector.get_user_transactions_all_chains(
                    wallet_address=wallet_address,
                    chains=request.chains
                )
                
                # Limit transactions per wallet
                wallet_transactions = wallet_transactions[:request.limit]
                
                # Filter by contract addresses if specified
                if request.contract_addresses:
                    wallet_transactions = [
                        tx for tx in wallet_transactions 
                        if tx.token_address.lower() in [addr.lower() for addr in request.contract_addresses]
                    ]
                
                all_transactions.extend(wallet_transactions)
                
                # Track which chains were successfully synced
                wallet_chains = list(set([tx.chain for tx in wallet_transactions]))
                chains_synced.extend(wallet_chains)
                
                logger.info(f"Found {len(wallet_transactions)} transactions for wallet {wallet_address}")
                
            except Exception as e:
                error_msg = f"Error syncing wallet {wallet_address}: {str(e)}"
                logger.error(error_msg)
                errors.append(error_msg)
        
        # Remove duplicates and sort by timestamp
        unique_transactions = {}
        for tx in all_transactions:
            key = f"{tx.tx_hash}_{tx.chain}"
            if key not in unique_transactions:
                unique_transactions[key] = tx
        
        final_transactions = list(unique_transactions.values())
        final_transactions.sort(key=lambda x: x.timestamp, reverse=True)
        
        # Convert to response format
        transaction_responses = []
        for tx in final_transactions:
            try:
                # Use the new Laravel-compatible format
                laravel_data = tx.to_laravel_format()
                
                transaction_responses.append(TransactionResponse(
                    tx_hash=laravel_data['tx_hash'],
                    from_address=laravel_data['from_address'],
                    to_address=laravel_data['to_address'],
                    value=laravel_data['value'],
                    token_symbol=laravel_data['token_symbol'],
                    token_address=laravel_data['token_address'],
                    block_number=laravel_data['block_number'],
                    timestamp=laravel_data['timestamp'],
                    gas_used=laravel_data['gas_used'],
                    gas_price=laravel_data['gas_price'],
                    transaction_type=laravel_data['transaction_type'],
                    chain=laravel_data['chain'],
                    project_id=laravel_data['project_id'],
                    source=laravel_data['source'],
                    is_verified=laravel_data['is_verified'],
                    raw_data=laravel_data['raw_data']
                ))
            except Exception as e:
                logger.warning(f"Error formatting transaction {tx.tx_hash}: {str(e)}")
                continue
        
        # Track API sources used
        api_sources_used = ["moralis", "etherscan", "bscscan", "polygonscan"]  # Based on chains
        
        response = TransactionSyncResponse(
            user_id=request.user_id,
            total_transactions=len(transaction_responses),
            transactions=transaction_responses,
            chains_synced=list(set(chains_synced)),
            sync_timestamp=datetime.now().isoformat(),
            errors=errors,
            api_sources_used=api_sources_used
        )
        
        logger.info(f"Transaction sync completed for user {request.user_id}: {len(transaction_responses)} transactions")
        
        return response
        
    except Exception as e:
        logger.error(f"Critical error in transaction sync: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Transaction sync failed: {str(e)}")

@router.post("/sync/single-wallet", response_model=TransactionSyncResponse)
async def sync_single_wallet_transactions(
    wallet_address: str,
    chains: List[str] = ['eth', 'bsc', 'polygon'],
    limit: int = 100
):
    """
    Sync transactions for a single wallet address (for testing)
    """
    logger.info(f"Syncing transactions for single wallet {wallet_address}")
    
    try:
        api_collector = get_collector()
        
        # Get transactions
        transactions = api_collector.get_user_transactions_all_chains(
            wallet_address=wallet_address,
            chains=chains
        )
        
        # Limit results
        transactions = transactions[:limit]
        
        # Convert to response format
        transaction_responses = []
        for tx in transactions:
            transaction_responses.append(TransactionResponse(
                tx_hash=tx.tx_hash,
                from_address=tx.from_address,
                to_address=tx.to_address,
                value=tx.value,
                token_symbol=tx.token_symbol,
                token_address=tx.token_address,
                block_number=tx.block_number,
                timestamp=tx.timestamp.isoformat(),
                gas_used=tx.gas_used,
                gas_price=tx.gas_price,
                transaction_type=tx.transaction_type,
                chain=tx.chain,
                project_id=tx.project_id
            ))
        
        response = TransactionSyncResponse(
            user_id="test_user",
            total_transactions=len(transaction_responses),
            transactions=transaction_responses,
            chains_synced=chains,
            sync_timestamp=datetime.now().isoformat(),
            errors=[],
            api_sources_used=["moralis", "etherscan"]
        )
        
        logger.info(f"Single wallet sync completed: {len(transaction_responses)} transactions")
        
        return response
        
    except Exception as e:
        logger.error(f"Error in single wallet sync: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Single wallet sync failed: {str(e)}")

@router.post("/prices/update", response_model=PriceUpdateResponse)
async def update_real_time_prices(request: PriceUpdateRequest):
    """
    Get real-time prices for specified token symbols
    """
    logger.info(f"Fetching real-time prices for {len(request.symbols)} symbols")
    
    try:
        api_collector = get_collector()
        
        if request.source == "binance":
            prices = api_collector.get_real_time_prices_binance(request.symbols)
        elif request.source == "coingecko":
            prices = api_collector.get_prices_coingecko(request.symbols)
        else:  # multiple sources
            prices = api_collector.get_real_time_prices_multiple_sources(request.symbols)
        
        symbols_found = list(prices.keys())
        symbols_missing = [s for s in request.symbols if s not in prices]
        
        response = PriceUpdateResponse(
            prices=prices,
            timestamp=datetime.now().isoformat(),
            source=request.source,
            symbols_found=symbols_found,
            symbols_missing=symbols_missing
        )
        
        logger.info(f"Price update completed: {len(symbols_found)} found, {len(symbols_missing)} missing")
        
        return response
        
    except Exception as e:
        logger.error(f"Error updating prices: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Price update failed: {str(e)}")

@router.get("/test/wallet/{wallet_address}")
async def test_wallet_transactions(wallet_address: str):
    """
    Test endpoint to check if wallet transaction fetching works
    """
    try:
        api_collector = get_collector()
        
        # Test with Ethereum only for speed
        transactions = api_collector.get_user_transactions_moralis(
            wallet_address=wallet_address,
            chain='eth',
            limit=5
        )
        
        if not transactions:
            # Try Etherscan as fallback
            transactions = api_collector.get_user_transactions_etherscan(wallet_address)[:5]
        
        result = {
            "wallet_address": wallet_address,
            "transactions_found": len(transactions),
            "sample_transactions": []
        }
        
        for tx in transactions[:3]:  # Show first 3 transactions
            result["sample_transactions"].append({
                "tx_hash": tx.tx_hash,
                "token_symbol": tx.token_symbol,
                "value": tx.value,
                "timestamp": tx.timestamp.isoformat(),
                "chain": tx.chain
            })
        
        return result
        
    except Exception as e:
        logger.error(f"Test wallet error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Test failed: {str(e)}")

@router.get("/status")
async def get_api_status():
    """
    Get status of all configured APIs
    """
    try:
        api_collector = get_collector()
        
        status = {
            "apis_configured": len(api_collector.apis),
            "api_status": {}
        }
        
        for api_name, api_config in api_collector.apis.items():
            status["api_status"][api_name] = {
                "name": api_config.name,
                "has_api_key": bool(api_config.api_key),
                "supported_chains": api_config.supported_chains,
                "rate_limit": api_config.rate_limit,
                "cost": api_config.cost,
                "reliability": api_config.reliability
            }
        
        return status
        
    except Exception as e:
        logger.error(f"Error getting API status: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Status check failed: {str(e)}")