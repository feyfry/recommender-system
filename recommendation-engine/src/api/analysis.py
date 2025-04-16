"""
API endpoints untuk analisis teknikal proyek Web3
"""

import os
import logging
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
import pandas as pd
import numpy as np
from fastapi import APIRouter, HTTPException, Query, Depends, Body, Path
from pydantic import BaseModel, Field

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

# Technical analysis imports
from src.technical.indicators import TechnicalIndicators
from src.technical.signals import generate_trading_signals, personalize_signals, detect_market_events

# Setup router
router = APIRouter(
    prefix="/analysis",
    tags=["technical analysis"],
    responses={404: {"description": "Not found"}},
)

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Cache for price data and signals
_price_data_cache = {}
_signals_cache = {}
_cache_ttl = 1800  # 30 minutes in seconds

# Mock API function to get price data (in a real app, this would fetch from a database or external API)
async def get_price_data(project_id: str, days: int = 30, interval: str = "1h") -> pd.DataFrame:
    """
    Get historical price data for a project
    
    Args:
        project_id: Project ID
        days: Number of days of data to fetch
        interval: Data interval ('1h', '1d', etc.)
        
    Returns:
        pd.DataFrame: DataFrame with price data
    """
    # Check cache first
    cache_key = f"{project_id}:{days}:{interval}"
    if cache_key in _price_data_cache:
        cache_entry = _price_data_cache[cache_key]
        
        # Check if cache is still valid
        if datetime.now() < cache_entry['expires']:
            logger.info(f"Returning cached price data for {cache_key}")
            return cache_entry['data']
    
    try:
        # In a real app, this would query a database or external API
        # For now, generate synthetic data for demo purposes
        
        # Number of data points
        if interval == "1h":
            n_points = days * 24
        elif interval == "1d":
            n_points = days
        elif interval == "15m":
            n_points = days * 24 * 4
        else:
            n_points = days
        
        # Generate timestamps
        end_date = datetime.now()
        start_date = end_date - timedelta(days=days)
        dates = pd.date_range(start=start_date, end=end_date, periods=n_points)
        
        # Set seed based on project_id for reproducibility but different per project
        seed = sum(ord(c) for c in project_id)
        rng = np.random.default_rng(seed)  # Create Generator instance with seed

        # Base price varies by project
        base_price = (seed % 1000) + 1  # $1 to $1000

        # Trend component (generally up or down based on project_id)
        trend = np.linspace(0, (seed % 200) - 100, n_points) / 100  # -1 to +1 trend

        # Volatility component
        volatility = max(0.001, min(0.1, (seed % 100) / 1000))  # 0.001 to 0.1

        # Generate price data with random walk
        # Using cumsum of normal random values for realistic price movements
        close = base_price * (1 + trend + np.cumsum(rng.normal(0, volatility, n_points)))
        high = close * (1 + rng.uniform(0, volatility * 2, n_points))
        low = close * (1 - rng.uniform(0, volatility * 2, n_points))
        open_prices = close * (1 + rng.normal(0, volatility, n_points))
        volume = rng.uniform(base_price * 1000, base_price * 10000, n_points)
        
        # Create DataFrame
        df = pd.DataFrame({
            'timestamp': dates,
            'open': open_prices,
            'high': high,
            'low': low,
            'close': close,
            'volume': volume
        }).set_index('timestamp')
        
        # Store in cache
        _price_data_cache[cache_key] = {
            'data': df,
            'expires': datetime.now() + timedelta(seconds=_cache_ttl)
        }
        
        return df
        
    except Exception as e:
        logger.error(f"Error fetching price data for {project_id}: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error fetching price data: {str(e)}")

# Pydantic models
class TradingSignalRequest(BaseModel):
    project_id: str
    days: int = Field(30, ge=1, le=365)
    interval: str = Field("1h", description="Price data interval")
    risk_tolerance: str = Field("medium", description="User risk tolerance (low, medium, high)")

class IndicatorValue(BaseModel):
    value: float
    signal: Optional[str] = None
    description: Optional[str] = None

class TradingSignalResponse(BaseModel):
    project_id: str
    action: str
    confidence: float
    strong_signal: bool
    evidence: List[str]
    target_price: Optional[float] = None
    personalized_message: Optional[str] = None
    risk_profile: Optional[str] = None
    indicators: Dict[str, float]
    timestamp: datetime

class TechnicalIndicatorsRequest(BaseModel):
    project_id: str
    days: int = Field(30, ge=1, le=365)
    interval: str = Field("1d", description="Price data interval")
    indicators: List[str] = ["rsi", "macd", "bollinger", "sma"]

class TechnicalIndicatorsResponse(BaseModel):
    project_id: str
    indicators: Dict[str, Dict[str, Any]]
    latest_close: float
    latest_timestamp: datetime
    period: str
    execution_time: float

class MarketEventResponse(BaseModel):
    project_id: str
    latest_event: str
    event_counts: Dict[str, int]
    close_price: float
    timestamp: datetime

# Routes
@router.post("/trading-signals", response_model=TradingSignalResponse)
async def get_trading_signals(request: TradingSignalRequest):
    """
    Get trading signals based on technical analysis
    """
    start_time = datetime.now()
    logger.info(f"Trading signal request for {request.project_id}")
    
    # Check cache first
    cache_key = f"{request.project_id}:{request.days}:{request.interval}:{request.risk_tolerance}"
    if cache_key in _signals_cache:
        cache_entry = _signals_cache[cache_key]
        
        # Check if cache is still valid
        if datetime.now() < cache_entry['expires']:
            logger.info(f"Returning cached trading signals for {cache_key}")
            
            # Update timestamp
            cache_entry['data'].timestamp = datetime.now()
            
            return cache_entry['data']
    
    try:
        # Get price data
        price_data = await get_price_data(
            request.project_id, 
            days=request.days, 
            interval=request.interval
        )
        
        # Generate trading signals
        signals = generate_trading_signals(price_data)
        
        # Personalize based on risk tolerance
        personalized = personalize_signals(signals, risk_tolerance=request.risk_tolerance)
        
        # Create response
        response = TradingSignalResponse(
            project_id=request.project_id,
            action=personalized.get('action', 'hold'),
            confidence=personalized.get('confidence', 0.5),
            strong_signal=personalized.get('strong_signal', False),
            evidence=personalized.get('evidence', []),
            target_price=personalized.get('target_price'),
            personalized_message=personalized.get('personalized_message'),
            risk_profile=personalized.get('risk_profile'),
            indicators=personalized.get('indicators', {}),
            timestamp=datetime.now()
        )
        
        # Store in cache
        _signals_cache[cache_key] = {
            'data': response,
            'expires': datetime.now() + timedelta(seconds=_cache_ttl)
        }
        
        return response
        
    except Exception as e:
        logger.error(f"Error generating trading signals: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.post("/indicators", response_model=TechnicalIndicatorsResponse)
async def get_technical_indicators(request: TechnicalIndicatorsRequest):
    """
    Calculate and return technical indicators for a project
    """
    start_time = datetime.now()
    logger.info(f"Technical indicators request for {request.project_id}")
    
    try:
        # Get price data
        price_data = await get_price_data(
            request.project_id, 
            days=request.days, 
            interval=request.interval
        )
        
        # Calculate indicators
        ti = TechnicalIndicators(price_data)
        df_with_indicators = ti.add_indicators()
        
        # Extract requested indicators from the last row
        latest_data = df_with_indicators.iloc[-1].to_dict()
        
        indicators_result = {}
        
        # RSI
        if "rsi" in request.indicators and "rsi" in latest_data:
            rsi_value = latest_data["rsi"]
            rsi_signal = "oversold" if rsi_value < 30 else "overbought" if rsi_value > 70 else "neutral"
            indicators_result["rsi"] = {
                "value": float(rsi_value),
                "signal": rsi_signal,
                "description": f"RSI is {rsi_signal} at {rsi_value:.2f}"
            }
        
        # MACD
        if "macd" in request.indicators and all(k in latest_data for k in ["macd", "macd_signal", "macd_hist"]):
            macd_value = latest_data["macd"]
            signal_value = latest_data["macd_signal"]
            hist_value = latest_data["macd_hist"]
            
            macd_signal = "bullish" if macd_value > signal_value else "bearish"
            
            # Check for crossover
            if df_with_indicators["macd_cross_up"].iloc[-1] == 1:
                macd_signal = "strong_bullish"
                description = "MACD crossed above signal line (strong buy)"
            elif df_with_indicators["macd_cross_down"].iloc[-1] == 1:
                macd_signal = "strong_bearish"
                description = "MACD crossed below signal line (strong sell)"
            else:
                description = f"MACD is {macd_signal} at {macd_value:.4f} (Signal: {signal_value:.4f})"
            
            indicators_result["macd"] = {
                "value": float(macd_value),
                "signal_line": float(signal_value),
                "histogram": float(hist_value),
                "signal": macd_signal,
                "description": description
            }
        
        # Bollinger Bands
        if "bollinger" in request.indicators and all(k in latest_data for k in ["bb_upper", "bb_middle", "bb_lower"]):
            upper = latest_data["bb_upper"]
            middle = latest_data["bb_middle"]
            lower = latest_data["bb_lower"]
            close = latest_data["close"]
            
            bb_pct = (close - lower) / (upper - lower) if upper > lower else 0.5
            
            if close > upper:
                bb_signal = "overbought"
                description = "Price is above upper Bollinger Band (potential reversal)"
            elif close < lower:
                bb_signal = "oversold"
                description = "Price is below lower Bollinger Band (potential reversal)"
            elif bb_pct > 0.8:
                bb_signal = "high"
                description = "Price is near upper Bollinger Band"
            elif bb_pct < 0.2:
                bb_signal = "low"
                description = "Price is near lower Bollinger Band"
            else:
                bb_signal = "neutral"
                description = "Price is within Bollinger Bands"
            
            indicators_result["bollinger"] = {
                "upper": float(upper),
                "middle": float(middle),
                "lower": float(lower),
                "percent_b": float(bb_pct),
                "signal": bb_signal,
                "description": description
            }
        
        # Moving Averages
        if "sma" in request.indicators:
            ma_data = {}
            ma_periods = [5, 10, 20, 50, 100, 200]
            
            for period in ma_periods:
                sma_key = f"sma_{period}"
                if sma_key in latest_data:
                    ma_data[f"sma_{period}"] = float(latest_data[sma_key])
            
            # Detect Golden/Death Cross
            if "golden_cross" in latest_data and latest_data["golden_cross"] == 1:
                ma_signal = "strong_bullish"
                description = "Golden Cross detected (50-day MA crossed above 200-day MA)"
            elif "death_cross" in latest_data and latest_data["death_cross"] == 1:
                ma_signal = "strong_bearish"
                description = "Death Cross detected (50-day MA crossed below 200-day MA)"
            elif "sma_20" in latest_data and "sma_50" in latest_data:
                if latest_data["close"] > latest_data["sma_20"] > latest_data["sma_50"]:
                    ma_signal = "bullish"
                    description = "Price is above 20 and 50-day moving averages (bullish trend)"
                elif latest_data["close"] < latest_data["sma_20"] < latest_data["sma_50"]:
                    ma_signal = "bearish"
                    description = "Price is below 20 and 50-day moving averages (bearish trend)"
                else:
                    ma_signal = "neutral"
                    description = "Moving average alignment is mixed"
            else:
                ma_signal = "neutral"
                description = "Insufficient moving average data"
            
            indicators_result["moving_averages"] = {
                "values": ma_data,
                "signal": ma_signal,
                "description": description
            }
        
        # Create response
        response = TechnicalIndicatorsResponse(
            project_id=request.project_id,
            indicators=indicators_result,
            latest_close=float(latest_data["close"]),
            latest_timestamp=df_with_indicators.index[-1],
            period=f"{request.days} days ({request.interval})",
            execution_time=(datetime.now() - start_time).total_seconds()
        )
        
        return response
        
    except Exception as e:
        logger.error(f"Error calculating technical indicators: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/market-events/{project_id}", response_model=MarketEventResponse)
async def get_market_events(
    project_id: str = Path(..., description="Project ID"),
    days: int = Query(30, ge=1, le=365),
    interval: str = Query("1h", description="Price data interval")
):
    """
    Detect market events such as pumps, dumps, high volatility, etc.
    """
    logger.info(f"Market events request for {project_id}")
    
    try:
        # Get price data
        price_data = await get_price_data(
            project_id, 
            days=days, 
            interval=interval
        )
        
        # Detect market events
        events = detect_market_events(price_data)
        
        # Create response
        response = MarketEventResponse(
            project_id=project_id,
            latest_event=events.get('latest_event', 'normal'),
            event_counts=events.get('event_counts', {}),
            close_price=float(price_data['close'].iloc[-1]),
            timestamp=price_data.index[-1]
        )
        
        return response
        
    except Exception as e:
        logger.error(f"Error detecting market events: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/alerts/{project_id}")
async def get_technical_alerts(
    project_id: str = Path(..., description="Project ID"),
    days: int = Query(30, ge=1, le=365),
    interval: str = Query("1d", description="Price data interval"),
    lookback: int = Query(5, ge=1, le=30, description="Number of periods to look back for alerts")
):
    """
    Get technical alerts for a project
    """
    logger.info(f"Technical alerts request for {project_id}")
    
    try:
        # Get price data
        price_data = await get_price_data(
            project_id, 
            days=days, 
            interval=interval
        )
        
        # Calculate indicators and generate alerts
        ti = TechnicalIndicators(price_data)
        df_with_indicators = ti.add_indicators()
        alerts = ti.generate_alerts(lookback_period=lookback)
        
        # Format response
        formatted_alerts = []
        for alert in alerts:
            formatted_alerts.append({
                "date": alert.get('date').isoformat(),
                "type": alert.get('type'),
                "message": alert.get('message'),
                "signal": alert.get('signal'),
                "strength": alert.get('strength')
            })
        
        return {
            "project_id": project_id,
            "alerts": formatted_alerts,
            "count": len(formatted_alerts),
            "period": f"{days} days ({interval})",
            "lookback": lookback
        }
        
    except Exception as e:
        logger.error(f"Error generating technical alerts: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/price-prediction/{project_id}")
async def predict_future_price(
    project_id: str = Path(..., description="Project ID"),
    days: int = Query(30, ge=1, le=365, description="Historical data days"),
    prediction_days: int = Query(7, ge=1, le=30, description="Days to predict"),
    interval: str = Query("1d", description="Price data interval")
):
    """
    Predict future price movement based on technical analysis
    This is a simplified prediction for demonstration purposes.
    """
    logger.info(f"Price prediction request for {project_id}")
    
    try:
        # Get price data
        price_data = await get_price_data(
            project_id, 
            days=days, 
            interval=interval
        )
        
        # Get trading signals
        signals = generate_trading_signals(price_data)
        
        # Calculate current price and ATR for volatility estimation
        current_price = float(price_data['close'].iloc[-1])
        
        # Use ATR for volatility if available
        if 'volume' in price_data.columns:
            from src.technical.signals import calculate_atr
            atr = calculate_atr(
                price_data['high'], 
                price_data['low'], 
                price_data['close']
            ).iloc[-1]
        else:
            # Estimate volatility from standard deviation of returns
            returns = price_data['close'].pct_change()
            atr = current_price * returns.std() * 2
        
        # Determine trend direction from signals
        action = signals.get('action', 'hold')
        confidence = signals.get('confidence', 0.5)
        
        # Set trend factor based on action and confidence
        if action == 'buy':
            trend_factor = confidence * 0.01  # 0-1% daily increase
        elif action == 'sell':
            trend_factor = -confidence * 0.01  # 0-1% daily decrease
        else:
            trend_factor = 0  # Neutral
        
        # Generate prediction dates
        last_date = price_data.index[-1]
        prediction_dates = pd.date_range(
            start=last_date + pd.Timedelta(days=1),
            periods=prediction_days,
            freq='D'
        )
        
        # Create RNG instance
        rng = np.random.default_rng(42)  # Use fixed seed for reproducibility

        # Generate predictions with random walk + trend
        predictions = []
        price = current_price
        
        for date in prediction_dates:
            # Add trend component and random noise
            daily_return = trend_factor + rng.normal(0, atr / price / 2)
            price = price * (1 + daily_return)
            
            # Calculate confidence with decreasing trend
            prediction_confidence = max(0.1, confidence - (len(predictions) * 0.05))
            
            predictions.append({
                "date": date.isoformat(),
                "predicted_price": price,
                "confidence": prediction_confidence
            })
        
        # Calculate prediction statistics
        final_price = predictions[-1]["predicted_price"]
        price_change = (final_price / current_price - 1) * 100

        # Determine prediction direction with clear logic steps
        if price_change > 0:
            prediction_direction = "up"
        elif price_change < 0:
            prediction_direction = "down"
        else:
            prediction_direction = "neutral"
        
        return {
            "project_id": project_id,
            "current_price": current_price,
            "prediction_direction": prediction_direction,
            "predicted_change_percent": price_change,
            "confidence": confidence,
            "predictions": predictions,
            "notes": "This is a simple prediction model for demonstration purposes only. Real-world predictions would use more sophisticated models."
        }
        
    except Exception as e:
        logger.error(f"Error generating price prediction: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

# Cache management endpoint
@router.post("/cache/clear")
async def clear_cache():
    """
    Clear analysis cache (admin only)
    """
    global _price_data_cache, _signals_cache
    
    try:
        price_cache_size = len(_price_data_cache)
        signals_cache_size = len(_signals_cache)
        
        _price_data_cache = {}
        _signals_cache = {}
        
        return {
            "message": f"Cache cleared ({price_cache_size} price entries, {signals_cache_size} signal entries)"
        }
    
    except Exception as e:
        logger.error(f"Error clearing cache: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")