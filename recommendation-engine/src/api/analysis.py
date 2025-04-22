"""
API endpoints untuk analisis teknikal proyek Web3
"""

import os
import logging
from typing import Dict, List, Optional, Any, Union
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
from src.data.collector import fetch_real_market_data

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
_cache_ttl = 300  # 5 minutes in seconds

# Function to get price data using real market data
async def get_price_data(project_id: str, days: int = 30, interval: str = "1d") -> pd.DataFrame:
    """
    Get historical price data for a project using real market data
    
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
        # Get real market data
        logger.info(f"Fetching real market data for {project_id}")
        df = fetch_real_market_data(project_id, days=days)
        
        if df.empty:
            logger.error(f"Failed to fetch market data for {project_id}")
            raise HTTPException(status_code=500, detail=f"Failed to fetch price data for {project_id}")
        
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
class IndicatorPeriods(BaseModel):
    rsi_period: int = Field(14, ge=3, le=50, description="Periode RSI (standard: 14)")
    macd_fast: int = Field(12, ge=5, le=50, description="Periode MACD fast EMA (standard: 12)")
    macd_slow: int = Field(26, ge=10, le=100, description="Periode MACD slow EMA (standard: 26)")
    macd_signal: int = Field(9, ge=3, le=50, description="Periode MACD signal line (standard: 9)")
    bb_period: int = Field(20, ge=5, le=50, description="Periode Bollinger Bands (standard: 20)")
    stoch_k: int = Field(14, ge=3, le=30, description="Periode Stochastic %K (standard: 14)")
    stoch_d: int = Field(3, ge=1, le=10, description="Periode Stochastic %D (standard: 3)")
    ma_short: int = Field(20, ge=5, le=50, description="Periode MA jangka pendek (standard: 20)")
    ma_medium: int = Field(50, ge=20, le=100, description="Periode MA jangka menengah (standard: 50)")
    ma_long: int = Field(200, ge=50, le=500, description="Periode MA jangka panjang (standard: 200)")
    
    class Config:
        schema_extra = {
            "example": {
                "rsi_period": 14,
                "macd_fast": 12,
                "macd_slow": 26,
                "macd_signal": 9,
                "bb_period": 20,
                "stoch_k": 14,
                "stoch_d": 3,
                "ma_short": 20,
                "ma_medium": 50,
                "ma_long": 200
            }
        }

class TradingSignalRequest(BaseModel):
    project_id: str
    days: int = Field(30, ge=1, le=365, description="Jumlah hari data historis")
    interval: str = Field("1d", description="Interval data ('1d', '1h', dsb)")
    risk_tolerance: str = Field("medium", description="Toleransi risiko pengguna (low, medium, high)")
    periods: Optional[IndicatorPeriods] = Field(None, description="Periode indikator teknikal")
    trading_style: str = Field("standard", description="Gaya trading ('short_term', 'standard', 'long_term')")

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
    indicator_periods: Dict[str, int]
    timestamp: datetime

class TechnicalIndicatorsRequest(BaseModel):
    project_id: str
    days: int = Field(30, ge=1, le=365)
    interval: str = Field("1d", description="Price data interval")
    indicators: List[str] = ["rsi", "macd", "bollinger", "sma"]
    periods: Optional[IndicatorPeriods] = Field(None, description="Periode indikator teknikal")

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
    logger.info(f"Trading signal request for {request.project_id} with days={request.days}")
    
    # Prepare indicator periods
    indicator_periods = {}
    
    # Handle trading style presets
    if request.trading_style == "short_term":
        indicator_periods = {
            'rsi_period': 7,
            'macd_fast': 8,
            'macd_slow': 17,
            'macd_signal': 9,
            'bb_period': 10,
            'stoch_k': 7,
            'stoch_d': 3,
            'ma_short': 10,
            'ma_medium': 30,
            'ma_long': 60
        }
        logger.info(f"Using short-term trading parameters (periode lebih pendek)")
    elif request.trading_style == "long_term":
        indicator_periods = {
            'rsi_period': 21,
            'macd_fast': 19,
            'macd_slow': 39,
            'macd_signal': 9,
            'bb_period': 30,
            'stoch_k': 21,
            'stoch_d': 7,
            'ma_short': 50,
            'ma_medium': 100,
            'ma_long': 200
        }
        logger.info(f"Using long-term trading parameters (periode lebih panjang)")
    else:  # standard
        indicator_periods = {
            'rsi_period': 14,
            'macd_fast': 12,
            'macd_slow': 26,
            'macd_signal': 9,
            'bb_period': 20,
            'stoch_k': 14,
            'stoch_d': 3,
            'ma_short': 20,
            'ma_medium': 50,
            'ma_long': 200
        }
        logger.info(f"Using standard trading parameters (periode default)")
    
    # Override dengan nilai kustom jika disediakan
    if request.periods:
        for key, value in request.periods.dict().items():
            indicator_periods[key] = value
    
    # Hitung minimal data yang diperlukan
    min_required_days = max(
        3 * indicator_periods['rsi_period'],
        indicator_periods['macd_slow'] + indicator_periods['macd_signal'] + 10,
        indicator_periods['bb_period'] + 10,
        indicator_periods['stoch_k'] + indicator_periods['stoch_d'] + 5,
        indicator_periods['ma_long'] + 10
    )
    
    # Sanity check: minimum 30 days
    min_required_days = max(30, min_required_days)
    
    # Check if request has valid number of days
    if request.days < min_required_days:
        logger.warning(f"Requested days {request.days} is below recommended minimum of {min_required_days} for selected parameters.")
        logger.info(f"Automatically adjusting requested days to {min_required_days}")
        request.days = min_required_days
    
    # Check cache first
    cache_key = f"{request.project_id}:{request.days}:{request.interval}:{request.risk_tolerance}:{request.trading_style}"
    
    # Add indicator periods to cache key if customized
    if request.periods:
        periods_str = ":".join([f"{k}={v}" for k, v in request.periods.dict().items()])
        cache_key += f":{periods_str}"
        
    if cache_key in _signals_cache:
        cache_entry = _signals_cache[cache_key]
        
        # Check if cache is still valid
        if datetime.now() < cache_entry['expires']:
            logger.info(f"Returning cached trading signals for {cache_key}")
            
            # Update timestamp
            cache_entry['data'].timestamp = datetime.now()
            
            return cache_entry['data']
    
    try:
        # Get real price data
        price_data = await get_price_data(
            request.project_id, 
            days=request.days, 
            interval=request.interval
        )
        
        if price_data.empty:
            logger.error(f"No price data available for {request.project_id}")
            raise HTTPException(
                status_code=404, 
                detail=f"No price data available for {request.project_id}"
            )
        
        logger.info(f"Got {len(price_data)} data points for {request.project_id}")
        
        # Check for sufficient data
        if len(price_data) < min_required_days:
            logger.warning(f"Insufficient data points for accurate technical analysis with selected parameters: {len(price_data)} < {min_required_days} recommended minimum")
            
            # Continue with warning but don't fail the request
            if len(price_data) < 20:
                # However, if there's really too little data, return a proper error
                raise HTTPException(
                    status_code=400, 
                    detail=f"Data tidak cukup untuk analisis teknikal. Tersedia {len(price_data)} titik data, dibutuhkan minimal 20. Coba tingkatkan parameter 'days'."
                )
        
        # Generate trading signals with custom periods
        signals = generate_trading_signals(price_data, indicator_periods=indicator_periods)
        
        # Check if the result contains an error
        if 'error' in signals:
            logger.error(f"Error in signal generation: {signals['error']}")
            
            # If there's a minimum days needed hint, include it in the response
            if 'min_days_needed' in signals:
                raise HTTPException(
                    status_code=400, 
                    detail=f"{signals['error']} Coba dengan parameter days={signals['min_days_needed']}."
                )
            else:
                raise HTTPException(
                    status_code=400, 
                    detail=signals['error']
                )
        
        # Personalize based on risk tolerance
        personalized = personalize_signals(signals, risk_tolerance=request.risk_tolerance)
        
        # Sanitize NaN values in indicators (enhance with more verbose messages)
        if 'indicators' in personalized:
            for key, value in list(personalized['indicators'].items()):
                if pd.isna(value):
                    logger.warning(f"NaN value detected for indicator {key}, replacing with 0.0")
                    personalized['indicators'][key] = 0.0
                    
        # Sanitize target_price if it's NaN
        if 'target_price' in personalized and pd.isna(personalized['target_price']):
            logger.warning("NaN value detected for target_price, removing from response")
            personalized.pop('target_price')
            
        # Sanitize confidence if it's NaN
        if 'confidence' in personalized and pd.isna(personalized['confidence']):
            logger.warning("NaN value detected for confidence, setting to default 0.5")
            personalized['confidence'] = 0.5
            
        # Check evidence quality
        if len(personalized.get('evidence', [])) == 0:
            logger.warning("No evidence found for trading signal")
            personalized['evidence'] = ["Not enough data for detailed signal analysis"]
        
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
            indicator_periods=personalized.get('indicator_periods', indicator_periods),
            timestamp=datetime.now()
        )
        
        # Log indicator values for debugging
        logger.info(f"MACD value: {response.indicators.get('macd', 'N/A')}")
        logger.info(f"RSI value: {response.indicators.get('rsi', 'N/A')}")
        
        # Store in cache
        _signals_cache[cache_key] = {
            'data': response,
            'expires': datetime.now() + timedelta(seconds=_cache_ttl)
        }
        
        return response
        
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"Error generating trading signals: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())  # Log the full traceback
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.post("/indicators", response_model=TechnicalIndicatorsResponse)
async def get_technical_indicators(request: TechnicalIndicatorsRequest):
    """
    Calculate and return technical indicators for a project
    """
    start_time = datetime.now()
    logger.info(f"Technical indicators request for {request.project_id}")
    
    # Handle custom periods
    indicator_periods = {}
    if request.periods:
        indicator_periods = request.periods.dict()
    
    try:
        # Get real price data
        price_data = await get_price_data(
            request.project_id, 
            days=request.days, 
            interval=request.interval
        )
        
        if price_data.empty:
            raise HTTPException(status_code=404, detail=f"No price data found for {request.project_id}")
        
        # Ensure all price columns are numeric
        for col in price_data.columns:
            price_data[col] = pd.to_numeric(price_data[col], errors='coerce')
        
        # Calculate indicators with error handling
        try:
            ti = TechnicalIndicators(price_data)
            df_with_indicators = ti.add_indicators()
        except Exception as e:
            logger.error(f"Error calculating indicators: {str(e)}")
            import traceback
            logger.error(traceback.format_exc())
            raise HTTPException(status_code=500, detail=f"Error calculating indicators: {str(e)}")
        
        # Extract requested indicators from the last row
        latest_data = df_with_indicators.iloc[-1].to_dict()
        
        # Fix any NaN or non-primitive values
        for k, v in list(latest_data.items()):
            if pd.isna(v):
                latest_data[k] = 0.0
            elif not isinstance(v, (int, float, bool, str)):
                # Convert non-primitive types to string
                latest_data[k] = str(v)
        
        indicators_result = {}
        
        # Safely extract indicators with complete error handling
        try:
            # RSI
            if "rsi" in request.indicators and "rsi" in latest_data:
                rsi_value = float(latest_data["rsi"])
                rsi_signal = "oversold" if rsi_value < 30 else "overbought" if rsi_value > 70 else "neutral"
                indicators_result["rsi"] = {
                    "value": rsi_value,
                    "signal": rsi_signal,
                    "description": f"RSI is {rsi_signal} at {rsi_value:.2f}",
                    "period": indicator_periods.get("rsi_period", 14)
                }
            
            # MACD
            if "macd" in request.indicators and all(k in latest_data for k in ["macd", "macd_signal"]):
                try:
                    macd_value = float(latest_data["macd"])
                    signal_value = float(latest_data["macd_signal"])
                    hist_value = float(latest_data.get("macd_hist", macd_value - signal_value))
                    
                    macd_signal = "bullish" if macd_value > signal_value else "bearish"
                    
                    # Check for crossover
                    macd_cross_up = False
                    macd_cross_down = False
                    if "macd_cross_up" in df_with_indicators.columns:
                        macd_cross_up = bool(df_with_indicators["macd_cross_up"].iloc[-1])
                    if "macd_cross_down" in df_with_indicators.columns:
                        macd_cross_down = bool(df_with_indicators["macd_cross_down"].iloc[-1])
                    
                    if macd_cross_up:
                        macd_signal = "strong_bullish"
                        description = "MACD crossed above signal line (strong buy)"
                    elif macd_cross_down:
                        macd_signal = "strong_bearish"
                        description = "MACD crossed below signal line (strong sell)"
                    else:
                        description = f"MACD is {macd_signal} at {macd_value:.4f} (Signal: {signal_value:.4f})"
                    
                    indicators_result["macd"] = {
                        "value": macd_value,
                        "signal_line": signal_value,
                        "histogram": hist_value,
                        "signal": macd_signal,
                        "description": description,
                        "periods": {
                            "fast": indicator_periods.get("macd_fast", 12),
                            "slow": indicator_periods.get("macd_slow", 26),
                            "signal": indicator_periods.get("macd_signal", 9)
                        }
                    }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating MACD indicators: {str(e)}")
            
            # Bollinger Bands
            if "bollinger" in request.indicators:
                try:
                    bb_columns = ["bb_upper", "bb_middle", "bb_lower"]
                    if all(col in latest_data for col in bb_columns):
                        upper = float(latest_data["bb_upper"])
                        middle = float(latest_data["bb_middle"])
                        lower = float(latest_data["bb_lower"])
                        close = float(latest_data["close"])
                        
                        # Calculate %B safely
                        bb_width = upper - lower
                        if bb_width > 0:
                            bb_pct = (close - lower) / bb_width
                        else:
                            bb_pct = 0.5
                        
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
                            "upper": upper,
                            "middle": middle,
                            "lower": lower,
                            "percent_b": bb_pct,
                            "signal": bb_signal,
                            "description": description,
                            "period": indicator_periods.get("bb_period", 20)
                        }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating Bollinger Band indicators: {str(e)}")
            
            # Moving Averages
            if "sma" in request.indicators:
                try:
                    ma_data = {}
                    short_period = indicator_periods.get("ma_short", 20)
                    medium_period = indicator_periods.get("ma_medium", 50)
                    long_period = indicator_periods.get("ma_long", 200)
                    
                    ma_periods = [short_period, medium_period, long_period]
                    
                    for period in ma_periods:
                        sma_key = f"sma_{period}"
                        if sma_key in latest_data:
                            ma_data[f"sma_{period}"] = float(latest_data[sma_key])
                    
                    # Define signals based on key moving averages
                    ma_signal = "neutral"
                    description = "Moving averages are neutral"
                    
                    # Detect Golden/Death Cross
                    if "golden_cross" in latest_data and latest_data["golden_cross"] == 1:
                        ma_signal = "strong_bullish"
                        description = f"Golden Cross detected ({medium_period}-day MA crossed above {long_period}-day MA)"
                    elif "death_cross" in latest_data and latest_data["death_cross"] == 1:
                        ma_signal = "strong_bearish"
                        description = f"Death Cross detected ({medium_period}-day MA crossed below {long_period}-day MA)"
                    elif f"sma_{short_period}" in latest_data and f"sma_{medium_period}" in latest_data:
                        close = float(latest_data["close"])
                        sma_short = float(latest_data[f"sma_{short_period}"])
                        sma_medium = float(latest_data[f"sma_{medium_period}"])
                        
                        if close > sma_short > sma_medium:
                            ma_signal = "bullish"
                            description = f"Price is above {short_period} and {medium_period}-day moving averages (bullish trend)"
                        elif close < sma_short < sma_medium:
                            ma_signal = "bearish"
                            description = f"Price is below {short_period} and {medium_period}-day moving averages (bearish trend)"
                    
                    indicators_result["moving_averages"] = {
                        "values": ma_data,
                        "signal": ma_signal,
                        "description": description,
                        "periods": {
                            "short": short_period,
                            "medium": medium_period,
                            "long": long_period
                        }
                    }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating Moving Average indicators: {str(e)}")
            
            # Stochastic Oscillator
            if "stochastic" in request.indicators:
                try:
                    if "stoch_k" in latest_data and "stoch_d" in latest_data:
                        k_value = float(latest_data["stoch_k"])
                        d_value = float(latest_data["stoch_d"])
                        
                        if k_value < 20:
                            stoch_signal = "oversold"
                            description = f"Stochastic Oscillator is oversold at {k_value:.2f}"
                        elif k_value > 80:
                            stoch_signal = "overbought"
                            description = f"Stochastic Oscillator is overbought at {k_value:.2f}"
                        else:
                            stoch_signal = "neutral"
                            description = f"Stochastic Oscillator is neutral at {k_value:.2f}"
                        
                        # Check for stochastic crossover
                        if "stoch_cross_up" in latest_data and latest_data["stoch_cross_up"] == 1:
                            if k_value < 20:
                                stoch_signal = "strong_buy"
                                description = "Stochastic %K crossed above %D from oversold (strong buy signal)"
                        elif "stoch_cross_down" in latest_data and latest_data["stoch_cross_down"] == 1:
                            if k_value > 80:
                                stoch_signal = "strong_sell"
                                description = "Stochastic %K crossed below %D from overbought (strong sell signal)"
                        
                        indicators_result["stochastic"] = {
                            "k": k_value,
                            "d": d_value,
                            "signal": stoch_signal,
                            "description": description,
                            "periods": {
                                "k": indicator_periods.get("stoch_k", 14),
                                "d": indicator_periods.get("stoch_d", 3)
                            }
                        }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating Stochastic indicators: {str(e)}")
            
            # ATR (Average True Range) - volatility indicator
            if "atr" in request.indicators:
                try:
                    if "atr" in latest_data:
                        atr_value = float(latest_data["atr"])
                        close = float(latest_data["close"])
                        
                        # Calculate ATR as percentage of price
                        atr_percent = (atr_value / close) * 100 if close > 0 else 0
                        
                        if atr_percent > 5:
                            volatility = "very_high"
                            description = f"Very high volatility (ATR: {atr_percent:.2f}% of price)"
                        elif atr_percent > 3:
                            volatility = "high"
                            description = f"High volatility (ATR: {atr_percent:.2f}% of price)"
                        elif atr_percent > 1.5:
                            volatility = "moderate"
                            description = f"Moderate volatility (ATR: {atr_percent:.2f}% of price)"
                        else:
                            volatility = "low"
                            description = f"Low volatility (ATR: {atr_percent:.2f}% of price)"
                        
                        indicators_result["atr"] = {
                            "value": atr_value,
                            "percent_of_price": atr_percent,
                            "volatility": volatility,
                            "description": description
                        }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating ATR indicators: {str(e)}")
            
            # ADX (Average Directional Index) - trend strength
            if "adx" in request.indicators:
                try:
                    if "adx" in latest_data:
                        adx_value = float(latest_data["adx"])
                        
                        if "plus_di" in latest_data and "minus_di" in latest_data:
                            plus_di = float(latest_data["plus_di"])
                            minus_di = float(latest_data["minus_di"])
                            
                            if adx_value > 25:
                                if plus_di > minus_di:
                                    trend_signal = "strong_uptrend"
                                    description = f"Strong uptrend (ADX: {adx_value:.2f}, +DI > -DI)"
                                else:
                                    trend_signal = "strong_downtrend"
                                    description = f"Strong downtrend (ADX: {adx_value:.2f}, -DI > +DI)"
                            elif adx_value > 20:
                                if plus_di > minus_di:
                                    trend_signal = "moderate_uptrend"
                                    description = f"Moderate uptrend (ADX: {adx_value:.2f}, +DI > -DI)"
                                else:
                                    trend_signal = "moderate_downtrend"
                                    description = f"Moderate downtrend (ADX: {adx_value:.2f}, -DI > +DI)"
                            else:
                                trend_signal = "weak_trend"
                                description = f"Weak trend (ADX: {adx_value:.2f})"
                        else:
                            # If DI values not available
                            if adx_value > 25:
                                trend_signal = "strong_trend"
                                description = f"Strong trend detected (ADX: {adx_value:.2f})"
                            elif adx_value > 20:
                                trend_signal = "moderate_trend"
                                description = f"Moderate trend (ADX: {adx_value:.2f})"
                            else:
                                trend_signal = "weak_trend"
                                description = f"Weak trend (ADX: {adx_value:.2f})"
                        
                        indicators_result["adx"] = {
                            "value": adx_value,
                            "plus_di": float(latest_data.get("plus_di", 0)),
                            "minus_di": float(latest_data.get("minus_di", 0)),
                            "trend": trend_signal,
                            "description": description
                        }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating ADX indicators: {str(e)}")
            
            # Volume indicators
            if "volume" in request.indicators and "volume" in latest_data:
                try:
                    volume_value = float(latest_data["volume"])
                    
                    # Volume analysis
                    if "volume_sma_20" in latest_data:
                        volume_avg = float(latest_data["volume_sma_20"])
                        volume_ratio = volume_value / volume_avg if volume_avg > 0 else 1.0
                        
                        if volume_ratio > 2.0:
                            volume_signal = "very_high"
                            description = f"Volume is {volume_ratio:.2f}x average (very high)"
                        elif volume_ratio > 1.5:
                            volume_signal = "high"
                            description = f"Volume is {volume_ratio:.2f}x average (high)"
                        elif volume_ratio < 0.5:
                            volume_signal = "low"
                            description = f"Volume is {volume_ratio:.2f}x average (low)"
                        else:
                            volume_signal = "normal"
                            description = f"Volume is {volume_ratio:.2f}x average (normal)"
                    else:
                        volume_signal = "unknown"
                        description = "Volume comparison not available"
                    
                    indicators_result["volume"] = {
                        "value": volume_value,
                        "signal": volume_signal,
                        "ratio": volume_ratio if 'volume_ratio' in locals() else None,
                        "description": description
                    }
                    
                    # On Balance Volume
                    if "obv" in latest_data:
                        obv_value = float(latest_data["obv"])
                        indicators_result["obv"] = {
                            "value": obv_value
                        }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating Volume indicators: {str(e)}")
            
            # Money Flow Index
            if "mfi" in request.indicators and "mfi" in latest_data:
                try:
                    mfi_value = float(latest_data["mfi"])
                    
                    if mfi_value < 20:
                        mfi_signal = "oversold"
                        description = f"Money Flow Index is oversold at {mfi_value:.2f}"
                    elif mfi_value > 80:
                        mfi_signal = "overbought"
                        description = f"Money Flow Index is overbought at {mfi_value:.2f}"
                    else:
                        mfi_signal = "neutral"
                        description = f"Money Flow Index is neutral at {mfi_value:.2f}"
                    
                    indicators_result["mfi"] = {
                        "value": mfi_value,
                        "signal": mfi_signal,
                        "description": description
                    }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating MFI indicators: {str(e)}")
            
            # Price ROC (Rate of Change)
            if "roc" in request.indicators and "roc" in latest_data:
                try:
                    roc_value = float(latest_data["roc"])
                    
                    if roc_value > 10:
                        roc_signal = "strong_bullish"
                        description = f"Very high rate of change: {roc_value:.2f}% (strongly bullish)"
                    elif roc_value > 5:
                        roc_signal = "bullish"
                        description = f"High rate of change: {roc_value:.2f}% (bullish)"
                    elif roc_value < -10:
                        roc_signal = "strong_bearish"
                        description = f"Very low rate of change: {roc_value:.2f}% (strongly bearish)"
                    elif roc_value < -5:
                        roc_signal = "bearish"
                        description = f"Low rate of change: {roc_value:.2f}% (bearish)"
                    else:
                        roc_signal = "neutral"
                        description = f"Moderate rate of change: {roc_value:.2f}% (neutral)"
                    
                    indicators_result["roc"] = {
                        "value": roc_value,
                        "signal": roc_signal,
                        "description": description
                    }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating ROC indicators: {str(e)}")
            
            # Williams %R
            if "willr" in request.indicators and "willr" in latest_data:
                try:
                    willr_value = float(latest_data["willr"])
                    
                    if willr_value < -80:
                        willr_signal = "oversold"
                        description = f"Williams %R is oversold at {willr_value:.2f}"
                    elif willr_value > -20:
                        willr_signal = "overbought"
                        description = f"Williams %R is overbought at {willr_value:.2f}"
                    else:
                        willr_signal = "neutral"
                        description = f"Williams %R is neutral at {willr_value:.2f}"
                    
                    indicators_result["willr"] = {
                        "value": willr_value,
                        "signal": willr_signal,
                        "description": description
                    }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating Williams %R indicators: {str(e)}")
            
            # Commodity Channel Index (CCI)
            if "cci" in request.indicators and "cci" in latest_data:
                try:
                    cci_value = float(latest_data["cci"])
                    
                    if cci_value > 100:
                        cci_signal = "overbought"
                        description = f"CCI is overbought at {cci_value:.2f}"
                    elif cci_value < -100:
                        cci_signal = "oversold"
                        description = f"CCI is oversold at {cci_value:.2f}"
                    else:
                        cci_signal = "neutral"
                        description = f"CCI is neutral at {cci_value:.2f}"
                    
                    indicators_result["cci"] = {
                        "value": cci_value,
                        "signal": cci_signal,
                        "description": description
                    }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating CCI indicators: {str(e)}")
                    
            # Overall signal based on multiple indicators
            if "overall_signal" in latest_data:
                try:
                    overall_signal = str(latest_data["overall_signal"])
                    
                    # Add to result
                    indicators_result["overall"] = {
                        "signal": overall_signal,
                        "buy_strength": float(latest_data.get("buy_strength", 50)),
                        "description": f"Overall signal: {overall_signal}"
                    }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating overall signal: {str(e)}")
            elif "buy_signals" in latest_data and "sell_signals" in latest_data:
                try:
                    buy_signals = int(latest_data["buy_signals"])
                    sell_signals = int(latest_data["sell_signals"])
                    
                    if buy_signals > sell_signals:
                        overall_signal = "buy"
                    elif sell_signals > buy_signals:
                        overall_signal = "sell"
                    else:
                        overall_signal = "hold"
                    
                    indicators_result["overall"] = {
                        "signal": overall_signal,
                        "buy_signals": buy_signals,
                        "sell_signals": sell_signals,
                        "description": f"Overall signal based on {buy_signals} buy vs {sell_signals} sell indicators"
                    }
                except (ValueError, TypeError) as e:
                    logger.warning(f"Error calculating overall signal from buy/sell counts: {str(e)}")
            
        except Exception as e:
            logger.error(f"Error processing indicator data: {str(e)}")
            import traceback
            logger.error(traceback.format_exc())
        
        # Create response
        response = TechnicalIndicatorsResponse(
            project_id=request.project_id,
            indicators=indicators_result,
            latest_close=float(latest_data.get("close", 0)),
            latest_timestamp=df_with_indicators.index[-1],
            period=f"{request.days} days ({request.interval})",
            execution_time=(datetime.now() - start_time).total_seconds()
        )
        
        return response
        
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"Error calculating technical indicators: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/market-events/{project_id}", response_model=MarketEventResponse)
async def get_market_events(
    project_id: str = Path(..., description="Project ID"),
    days: int = Query(40, ge=1, le=365),
    interval: str = Query("1d", description="Price data interval")
):
    """
    Detect market events such as pumps, dumps, high volatility, etc.
    """
    logger.info(f"Market events request for {project_id}")
    
    try:
        # Get real price data
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
        # Get real price data
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
    days: int = Query(50, ge=1, le=365, description="Historical data days"),
    prediction_days: int = Query(7, ge=1, le=50, description="Days to predict"),
    interval: str = Query("1d", description="Price data interval")
):
    """
    Predict future price movement based on technical analysis
    """
    logger.info(f"Price prediction request for {project_id}")
    
    try:
        # Get real price data
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
        if 'high' in price_data.columns and 'low' in price_data.columns:
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
            "data_source": "Real market data"
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