import os
import logging
from typing import Dict, List, Optional, Any, Tuple, Union
from datetime import datetime, timedelta
import pandas as pd
import numpy as np
from fastapi import APIRouter, HTTPException, Query, Depends, Body, Path
from pydantic import BaseModel, Field
import time

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

# Technical analysis imports
from src.technical.indicators import TechnicalIndicators, predict_price_ml, predict_price_arima, predict_price_simple
from src.technical.signals import (
    generate_trading_signals, 
    personalize_signals, 
    detect_market_events, 
    detect_market_regime, 
    get_optimal_parameters,
    weighted_signal_ensemble
)
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

# Nonaktifkan beberapa warning yang tidak perlu dari library lain
import warnings
# Konfigurasi untuk meredam warning TensorFlow
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '2'  # 0=DEBUG, 1=INFO, 2=WARNING, 3=ERROR
warnings.filterwarnings("ignore", category=FutureWarning)
warnings.filterwarnings("ignore", category=UserWarning)
warnings.filterwarnings("ignore", message="No supported index is available")
warnings.filterwarnings("ignore", message="A date index has been provided")
warnings.filterwarnings("ignore", message="Do not pass an `input_shape`/`input_dim` argument to a layer")

# Untuk statsmodels
try:
    from statsmodels.tools.sm_exceptions import ValueWarning
    warnings.filterwarnings("ignore", category=ValueWarning)
except ImportError:
    pass

# Cache for price data and signals
_price_data_cache = {}
_signals_cache = {}
_cache_ttl = 300  # 5 minutes in seconds

# Function to get price data using real market data
async def get_price_data(project_id: str, days: int = 30, interval: str = "1d") -> pd.DataFrame:
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

def get_optimal_timeframe(price_data: pd.DataFrame, request: Any) -> Tuple[int, str]:
    # Hitung volatilitas harian
    if 'close' in price_data.columns:
        returns = price_data['close'].pct_change().dropna()
        daily_volatility = returns.std()
        
        # Tentukan timeframe berdasarkan volatilitas dan tujuan
        trading_style = getattr(request, 'trading_style', 'standard')
        
        if trading_style == 'short_term':
            # Untuk trading jangka pendek, gunakan data yang lebih sedikit
            # tapi dengan interval yang lebih rapat
            if daily_volatility > 0.05:  # Volatilitas tinggi
                return min(60, request.days), '15m'
            else:
                return min(90, request.days), '1h'
        elif trading_style == 'long_term':
            # Untuk trading jangka panjang, gunakan lebih banyak data historis
            return max(180, request.days), '1d'
        else:  # standard
            # Untuk keseimbangan
            return request.days, '1d'
    
    # Default jika kolom close tidak ada
    return request.days, '1d'

def _extract_trend_indicators(df: pd.DataFrame, latest_data: Dict[str, Any], 
                          indicator_periods: Dict[str, Any]) -> Dict[str, Any]:
    ma_short = indicator_periods.get('ma_short', 20)
    ma_medium = indicator_periods.get('ma_medium', 50)
    ma_long = indicator_periods.get('ma_long', 200)
    
    result = {}
    
    # Moving Averages
    mas = {}
    for period in [ma_short, ma_medium, ma_long]:
        sma_key = f'sma_{period}'
        ema_key = f'ema_{period}'
        
        if sma_key in latest_data:
            mas[f'sma_{period}'] = float(latest_data[sma_key])
        if ema_key in latest_data:
            mas[f'ema_{period}'] = float(latest_data[ema_key])
    
    result['moving_averages'] = mas
    
    # MACD
    if all(k in latest_data for k in ['macd', 'macd_signal', 'macd_hist']):
        result['macd'] = {
            'value': float(latest_data['macd']),
            'signal': float(latest_data['macd_signal']),
            'histogram': float(latest_data['macd_hist']),
            'cross_up': bool(latest_data.get('macd_cross_up', False)),
            'cross_down': bool(latest_data.get('macd_cross_down', False)),
            'signal_type': latest_data.get('macd_signal_type', 'neutral')
        }
    
    # ADX
    if all(k in latest_data for k in ['adx', 'plus_di', 'minus_di']):
        result['adx'] = {
            'value': float(latest_data['adx']),
            'plus_di': float(latest_data['plus_di']),
            'minus_di': float(latest_data['minus_di']),
            'trend_strength': "strong" if float(latest_data['adx']) > 25 else "weak",
            'trend_direction': "bullish" if float(latest_data['plus_di']) > float(latest_data['minus_di']) else "bearish"
        }
    
    # Moving Average Crossovers
    if 'golden_cross' in latest_data:
        result['ma_crossovers'] = {
            'golden_cross': bool(latest_data.get('golden_cross', False)),
            'death_cross': bool(latest_data.get('death_cross', False)),
            'short_medium_cross_up': bool(latest_data.get('sma_short_medium_cross_up', False)),
            'short_medium_cross_down': bool(latest_data.get('sma_short_medium_cross_down', False))
        }
    
    return result

def _extract_momentum_indicators(df: pd.DataFrame, latest_data: Dict[str, Any], 
                            indicator_periods: Dict[str, Any]) -> Dict[str, Any]:
    result = {}
    
    # RSI
    if 'rsi' in latest_data:
        rsi_value = float(latest_data['rsi'])
        result['rsi'] = {
            'value': rsi_value,
            'signal': "oversold" if rsi_value < 30 else "overbought" if rsi_value > 70 else "neutral",
            'description': f"RSI is at {rsi_value:.2f}",
            'period': indicator_periods.get('rsi_period', 14)
        }
    
    # Stochastic
    if all(k in latest_data for k in ['stoch_k', 'stoch_d']):
        k_value = float(latest_data['stoch_k'])
        d_value = float(latest_data['stoch_d'])
        
        if k_value < 20:
            stoch_signal = "oversold"
        elif k_value > 80:
            stoch_signal = "overbought"
        else:
            stoch_signal = "neutral"
            
        result['stochastic'] = {
            'k': k_value,
            'd': d_value,
            'signal': stoch_signal,
            'cross_up': bool(latest_data.get('stoch_cross_up', False)),
            'cross_down': bool(latest_data.get('stoch_cross_down', False)),
            'description': f"Stochastic oscillator is {stoch_signal} at {k_value:.2f}",
            'period_k': indicator_periods.get('stoch_k', 14),
            'period_d': indicator_periods.get('stoch_d', 3)
        }
    
    # ROC
    if 'roc' in latest_data:
        roc_value = float(latest_data['roc'])
        result['roc'] = {
            'value': roc_value,
            'signal': "bullish" if roc_value > 0 else "bearish",
            'description': f"Rate of Change is {roc_value:.2f}%",
            'period': indicator_periods.get('roc_period', 10)
        }
    
    # Williams %R
    if 'willr' in latest_data:
        willr_value = float(latest_data['willr'])
        result['willr'] = {
            'value': willr_value,
            'signal': "oversold" if willr_value < -80 else "overbought" if willr_value > -20 else "neutral",
            'description': f"Williams %R is at {willr_value:.2f}",
            'period': indicator_periods.get('willr_period', 14)
        }
    
    # CCI
    if 'cci' in latest_data:
        cci_value = float(latest_data['cci'])
        result['cci'] = {
            'value': cci_value,
            'signal': "oversold" if cci_value < -100 else "overbought" if cci_value > 100 else "neutral",
            'description': f"CCI is at {cci_value:.2f}",
            'period': indicator_periods.get('cci_period', 20)
        }
    
    # RSI Divergence
    if any(k in latest_data for k in ['bullish_divergence', 'bearish_divergence']):
        result['divergence'] = {
            'bullish': bool(latest_data.get('bullish_divergence', False)),
            'bearish': bool(latest_data.get('bearish_divergence', False)),
            'description': "Divergence detected" if any(latest_data.get(k, False) for k in ['bullish_divergence', 'bearish_divergence']) else "No divergence"
        }
        
    return result

def _extract_volatility_indicators(df: pd.DataFrame, latest_data: Dict[str, Any], 
                              indicator_periods: Dict[str, Any]) -> Dict[str, Any]:
    result = {}
    
    # Bollinger Bands
    if all(k in latest_data for k in ['bb_upper', 'bb_middle', 'bb_lower']):
        upper = float(latest_data['bb_upper'])
        middle = float(latest_data['bb_middle'])
        lower = float(latest_data['bb_lower'])
        close = float(latest_data['close'])
        
        # Calculate %B
        if upper != lower:
            pct_b = (close - lower) / (upper - lower)
        else:
            pct_b = 0.5
        
        if pct_b > 1:
            bb_signal = "overbought"
        elif pct_b < 0:
            bb_signal = "oversold"
        elif pct_b > 0.8:
            bb_signal = "high"
        elif pct_b < 0.2:
            bb_signal = "low"
        else:
            bb_signal = "neutral"
            
        result['bollinger'] = {
            'upper': upper,
            'middle': middle,
            'lower': lower,
            'percent_b': pct_b,
            'bandwidth': float(latest_data.get('bb_bandwidth', (upper - lower) / middle if middle != 0 else 0)),
            'signal': bb_signal,
            'description': f"Price is {bb_signal} relative to Bollinger Bands (%B: {pct_b:.2f})",
            'period': indicator_periods.get('bb_period', 20)
        }
    
    # ATR
    if 'atr' in latest_data:
        atr_value = float(latest_data['atr'])
        atr_pct = float(latest_data.get('atr_pct', atr_value / float(latest_data['close']) * 100 if float(latest_data['close']) != 0 else 0))
        
        # Assess volatility
        if atr_pct > 5:
            volatility = "very_high"
        elif atr_pct > 3:
            volatility = "high"
        elif atr_pct > 1.5:
            volatility = "moderate"
        else:
            volatility = "low"
            
        result['atr'] = {
            'value': atr_value,
            'percent': atr_pct,
            'volatility': volatility,
            'description': f"{volatility.capitalize()} volatility (ATR: {atr_pct:.2f}% of price)",
            'period': indicator_periods.get('atr_period', 14)
        }
    
    # Keltner Channels
    if all(k in latest_data for k in ['keltner_upper', 'keltner_middle', 'keltner_lower']):
        result['keltner'] = {
            'upper': float(latest_data['keltner_upper']),
            'middle': float(latest_data['keltner_middle']),
            'lower': float(latest_data['keltner_lower']),
            'width': float(latest_data['keltner_upper']) - float(latest_data['keltner_lower'])
        }
        
    # Volatility Squeeze
    if 'squeeze_on' in latest_data:
        result['squeeze'] = {
            'active': bool(latest_data['squeeze_on']),
            'starting': bool(latest_data.get('squeeze_starting', False)),
            'releasing': bool(latest_data.get('squeeze_releasing', False)),
            'description': "Volatility squeeze active" if latest_data['squeeze_on'] else "No volatility squeeze"
        }
        
    # Historical Volatility
    if 'volatility_21d' in latest_data:
        vol_21d = float(latest_data['volatility_21d'])
        result['historical_volatility'] = {
            '21d': vol_21d,
            '63d': float(latest_data.get('volatility_63d', 0)),
            'annualized': vol_21d * np.sqrt(252),
            'description': f"21-day historical volatility: {vol_21d * 100:.2f}%"
        }
        
    return result

def _extract_volume_indicators(df: pd.DataFrame, latest_data: Dict[str, Any]) -> Dict[str, Any]:
    result = {}
    
    # Volume
    if 'volume' in latest_data:
        volume = float(latest_data['volume'])
        volume_ratio = float(latest_data.get('volume_ratio', 1.0))
        
        if volume_ratio > 2:
            volume_signal = "very_high"
        elif volume_ratio > 1.5:
            volume_signal = "high"
        elif volume_ratio < 0.5:
            volume_signal = "low"
        else:
            volume_signal = "normal"
            
        result['volume'] = {
            'value': volume,
            'ratio': volume_ratio,
            'signal': volume_signal,
            'description': f"Volume is {volume_ratio:.2f}x average ({volume_signal})"
        }
    
    # OBV
    if 'obv' in latest_data:
        result['obv'] = {
            'value': float(latest_data['obv']),
            'direction': "up" if latest_data.get('obv_direction', 0) > 0 else "down"
        }
    
    # Money Flow Index
    if 'mfi' in latest_data:
        mfi_value = float(latest_data['mfi'])
        result['mfi'] = {
            'value': mfi_value,
            'signal': "oversold" if mfi_value < 20 else "overbought" if mfi_value > 80 else "neutral",
            'description': f"Money Flow Index is {mfi_value:.2f}"
        }
    
    # Volume Spikes
    if 'volume_spike' in latest_data:
        result['volume_spike'] = {
            'active': bool(latest_data['volume_spike']),
            'up': bool(latest_data.get('volume_spike_up', False)),
            'down': bool(latest_data.get('volume_spike_down', False)),
            'description': "Volume spike detected" if latest_data['volume_spike'] else "No volume spike"
        }
        
    # Volume Divergence
    if any(k in latest_data for k in ['bullish_vol_div', 'bearish_vol_div']):
        result['volume_divergence'] = {
            'bullish': bool(latest_data.get('bullish_vol_div', False)),
            'bearish': bool(latest_data.get('bearish_vol_div', False)),
            'description': "Volume divergence detected" if any(latest_data.get(k, False) for k in ['bullish_vol_div', 'bearish_vol_div']) else "No volume divergence"
        }
        
    return result

def _extract_ichimoku_indicators(df: pd.DataFrame, latest_data: Dict[str, Any]) -> Dict[str, Any]:
    if not all(k in latest_data for k in ['tenkan_sen', 'kijun_sen', 'senkou_span_a', 'senkou_span_b']):
        return {}
    
    result = {
        'tenkan': float(latest_data['tenkan_sen']),
        'kijun': float(latest_data['kijun_sen']),
        'senkou_a': float(latest_data['senkou_span_a']),
        'senkou_b': float(latest_data['senkou_span_b'])
    }
    
    # Chikou Span if available
    if 'chikou_span' in latest_data:
        result['chikou'] = float(latest_data['chikou_span'])
    
    # Cloud color/type
    result['cloud_green'] = bool(latest_data.get('cloud_green', False))
    result['cloud_red'] = bool(latest_data.get('cloud_red', False))
    
    # Price position
    result['price_above_cloud'] = bool(latest_data.get('price_above_cloud', False))
    result['price_in_cloud'] = bool(latest_data.get('price_in_cloud', False))
    result['price_below_cloud'] = bool(latest_data.get('price_below_cloud', False))
    
    # TK Cross
    result['tk_cross_bull'] = bool(latest_data.get('tk_cross_bull', False))
    result['tk_cross_bear'] = bool(latest_data.get('tk_cross_bear', False))
    
    # Signal
    if result['price_above_cloud'] and result['cloud_green']:
        signal = "strong_bullish"
    elif result['price_below_cloud'] and result['cloud_red']:
        signal = "strong_bearish"
    elif result['price_above_cloud']:
        signal = "bullish"
    elif result['price_below_cloud']:
        signal = "bearish"
    elif result['price_in_cloud'] and result['cloud_green']:
        signal = "neutral_bullish"
    elif result['price_in_cloud'] and result['cloud_red']:
        signal = "neutral_bearish"
    else:
        signal = "neutral"
        
    result['signal'] = signal
    
    # Description
    if latest_data.get('tk_cross_bull', False):
        result['description'] = "Bullish TK Cross detected"
    elif latest_data.get('tk_cross_bear', False):
        result['description'] = "Bearish TK Cross detected"
    else:
        result['description'] = f"Ichimoku Cloud is {signal.replace('_', ' ')}"
        
    return result

def _extract_oscillator_composite(df: pd.DataFrame, latest_data: Dict[str, Any]) -> Dict[str, Any]:
    if 'momentum_composite' not in latest_data:
        return {}
    
    composite_value = float(latest_data['momentum_composite'])
    
    if composite_value > 70:
        signal = "overbought"
    elif composite_value < 30:
        signal = "oversold"
    elif composite_value > 60:
        signal = "high"
    elif composite_value < 40:
        signal = "low"
    else:
        signal = "neutral"
        
    return {
        'value': composite_value,
        'signal': signal,
        'components': latest_data.get('momentum_signal', 'neutral'),
        'description': f"Combined oscillator value is {composite_value:.2f} ({signal})"
    }

def _get_regime_description(market_regime: str) -> str:
    descriptions = {
        "trending_bullish": "Strong uptrend with normal volatility",
        "trending_bullish_volatile": "Strong uptrend with high volatility",
        "trending_bearish": "Strong downtrend with normal volatility",
        "trending_bearish_volatile": "Strong downtrend with high volatility",
        "trending_neutral": "Market is trending but direction is unclear",
        "ranging_volatile": "Sideways market with high volatility",
        "ranging_low_volatility": "Sideways market with low volatility",
        "volatile_bullish": "Bullish market with very high volatility",
        "volatile_bearish": "Bearish market with very high volatility",
        "volatile_sideways": "Sideways market with extremely high volatility"
    }
    
    return descriptions.get(market_regime, "Unknown market conditions")

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
        json_schema_extra = {
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
    auto_optimize: bool = Field(True, description="Otomatis optimasi parameter berdasarkan market regime")

class IndicatorValue(BaseModel):
    value: float
    signal: Optional[str] = None
    description: Optional[str] = None

class ReversalSignal(BaseModel):
    type: str
    description: str
    strength: float

class TradingSignalResponse(BaseModel):
    project_id: str
    action: str
    confidence: float
    strong_signal: bool
    evidence: List[str]
    target_price: Optional[float] = None
    target_2: Optional[float] = None
    support_1: Optional[float] = None
    support_2: Optional[float] = None
    personalized_message: Optional[str] = None
    risk_profile: Optional[str] = None
    indicators: Dict[str, float]
    indicator_periods: Dict[str, int]
    market_regime: Optional[str] = None
    trend_direction: Optional[str] = None
    buy_score: Optional[float] = None
    sell_score: Optional[float] = None
    reversal_probability: Optional[float] = None
    reversal_signals: Optional[List[ReversalSignal]] = None
    timestamp: datetime

class TechnicalIndicatorsRequest(BaseModel):
    project_id: str
    days: int = Field(30, ge=1, le=365)
    interval: str = Field("1d", description="Price data interval")
    indicators: List[str] = ["rsi", "macd", "bollinger", "sma", "stochastic", "adx", "atr", "ichimoku"]
    periods: Optional[IndicatorPeriods] = Field(None, description="Periode indikator teknikal")
    trading_style: str = Field("standard", description="Gaya trading ('short_term', 'standard', 'long_term')")
    auto_optimize: bool = Field(True, description="Otomatis optimasi parameter berdasarkan market regime")

class TechnicalIndicatorsResponse(BaseModel):
    project_id: str
    indicators: Dict[str, Dict[str, Any]]
    latest_close: float
    market_regime: str
    latest_timestamp: datetime
    period: str
    execution_time: float

class MarketEventResponse(BaseModel):
    project_id: str
    latest_event: str
    market_regime: str
    event_counts: Dict[str, int]
    recent_events: Dict[str, List[str]]
    close_price: float
    timestamp: datetime

class PredictionDataPoint(BaseModel):
    date: str
    value: float
    confidence: float

class PricePredictionResponse(BaseModel):
    project_id: str
    current_price: float
    prediction_direction: str
    predicted_change_percent: float
    confidence: float
    model_type: str
    market_regime: str
    reversal_probability: Optional[float] = None
    support_levels: Optional[Dict[str, float]] = None
    resistance_levels: Optional[Dict[str, float]] = None
    predictions: List[PredictionDataPoint]
    timestamp: datetime

# Routes
@router.post("/trading-signals", response_model=TradingSignalResponse)
async def get_trading_signals(request: TradingSignalRequest):
    start_time = datetime.now()
    logger.info(f"Trading signal request for {request.project_id} with days={request.days}")
    
    try:
        # Get optimal timeframe based on trading style and data characteristics
        optimal_days, optimal_interval = request.days, request.interval
        
        if request.auto_optimize:
            # Get a small sample of data first to determine characteristics
            sample_data = await get_price_data(
                request.project_id, 
                days=min(30, request.days), 
                interval=request.interval
            )
            
            optimal_days, optimal_interval = get_optimal_timeframe(sample_data, request)
            
            if optimal_days != request.days or optimal_interval != request.interval:
                logger.info(f"Optimized timeframe from {request.days}d/{request.interval} to {optimal_days}d/{optimal_interval}")
        
        # Get price data with optimal timeframe
        price_data = await get_price_data(
            request.project_id, 
            days=optimal_days, 
            interval=optimal_interval
        )
        
        # Ensure price_data has necessary columns (PERBAIKAN)
        if 'close' not in price_data.columns and 'price' in price_data.columns:
            price_data['close'] = price_data['price']
        
        # Detect market regime
        market_regime = detect_market_regime(price_data)
        logger.info(f"Detected market regime: {market_regime}")
        
        # Optimize parameters if requested
        if request.auto_optimize:
            if request.periods:
                # Use provided parameters but enhance them for this market regime
                indicator_periods = request.periods.dict()
                optimized_params = get_optimal_parameters(price_data, market_regime, request.trading_style)
                
                # Merge the parameters, keeping user-specified ones
                for key, value in optimized_params.items():
                    if key not in indicator_periods:
                        indicator_periods[key] = value
                        
                logger.info(f"Using optimized parameters for {market_regime} regime with user customizations")
            else:
                # Use fully optimized parameters
                indicator_periods = get_optimal_parameters(price_data, market_regime, request.trading_style)
                logger.info(f"Using fully optimized parameters for {market_regime} regime")
        else:
            # Use provided parameters or defaults
            indicator_periods = request.periods.dict() if request.periods else None
        
        # Generate trading signals
        signals = generate_trading_signals(price_data, indicator_periods)
        
        # Personalize based on preferences
        personalized = personalize_signals(signals, risk_tolerance=request.risk_tolerance)
        
        # Get additional technical analysis
        ti = TechnicalIndicators(price_data, indicator_periods)
        reversal_data = ti.generate_reversal_signals()
        
        # Add trend prediction
        trend_prediction = ti.predict_price_trend(periods=5)
        
        # PERBAIKAN: Format reversal signals dengan konsisten
        formatted_reversal_signals = []
        if reversal_data and 'signals' in reversal_data and reversal_data['signals']:
            for signal in reversal_data['signals']:
                if isinstance(signal, dict):
                    # Signal sudah dalam format object yang benar
                    formatted_reversal_signals.append({
                        "type": signal.get('type', 'unknown'),
                        "description": signal.get('description', 'Unknown signal'),
                        "strength": float(signal.get('strength', 0.5))
                    })
                elif isinstance(signal, str):
                    # Convert string signal ke object format
                    formatted_reversal_signals.append({
                        "type": "general",
                        "description": signal,
                        "strength": 0.5
                    })
        
        # Buat response
        response = TradingSignalResponse(
            project_id=request.project_id,
            action=personalized.get('action', 'hold'),
            confidence=personalized.get('confidence', 0.5),
            strong_signal=personalized.get('strong_signal', False),
            evidence=personalized.get('evidence', []),
            target_price=personalized.get('target_price'),
            target_2=trend_prediction.get('target_2'),
            support_1=trend_prediction.get('support_1'),
            support_2=trend_prediction.get('support_2'),
            personalized_message=personalized.get('personalized_message'),
            risk_profile=personalized.get('risk_profile'),
            indicators=personalized.get('indicators', {}),
            indicator_periods=personalized.get('indicator_periods', indicator_periods),
            market_regime=market_regime,
            trend_direction=signals.get('trend_direction', 'neutral'),
            buy_score=signals.get('buy_score', 0.5),
            sell_score=signals.get('sell_score', 0.5),
            reversal_probability=reversal_data.get('reversal_probability', 0.0),
            reversal_signals=formatted_reversal_signals,  # PERBAIKAN: Gunakan format yang sudah dinormalisasi
            timestamp=datetime.now()
        )
        
        return response
        
    except HTTPException as he:
        # Re-raise HTTP exceptions
        raise he
    except Exception as e:
        logger.error(f"Error generating trading signals: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.post("/indicators", response_model=TechnicalIndicatorsResponse)
async def get_technical_indicators(request: TechnicalIndicatorsRequest):
    start_time = datetime.now()
    logger.info(f"Technical indicators request for {request.project_id}")
    
    try:
        # Optimize parameters based on market conditions
        if request.auto_optimize:
            # Get a small sample of data first to determine characteristics
            sample_data = await get_price_data(
                request.project_id, 
                days=min(30, request.days), 
                interval=request.interval
            )
            
            optimal_days, optimal_interval = get_optimal_timeframe(sample_data, request)
            
            if optimal_days != request.days or optimal_interval != request.interval:
                logger.info(f"Optimized timeframe from {request.days}d/{request.interval} to {optimal_days}d/{optimal_interval}")
                
            # Get price data with optimal timeframe
            price_data = await get_price_data(
                request.project_id, 
                days=optimal_days, 
                interval=optimal_interval
            )
        else:
            # Use requested parameters directly
            price_data = await get_price_data(
                request.project_id, 
                days=request.days, 
                interval=request.interval
            )
        
        if price_data.empty:
            raise HTTPException(status_code=404, detail=f"No price data found for {request.project_id}")
        
        # Detect market regime
        market_regime = detect_market_regime(price_data)
        logger.info(f"Detected market regime: {market_regime}")
        
        # Determine parameters
        if request.auto_optimize:
            if request.periods:
                # Use provided parameters but enhance them for this market regime
                indicator_periods = request.periods.dict()
                optimized_params = get_optimal_parameters(price_data, market_regime, request.trading_style)
                
                # Merge the parameters, keeping user-specified ones
                for key, value in optimized_params.items():
                    if key not in indicator_periods:
                        indicator_periods[key] = value
                        
                logger.info(f"Using optimized parameters for {market_regime} regime with user customizations")
            else:
                # Use fully optimized parameters
                indicator_periods = get_optimal_parameters(price_data, market_regime, request.trading_style)
                logger.info(f"Using fully optimized parameters for {market_regime} regime")
        else:
            # Use provided parameters or defaults
            indicator_periods = request.periods.dict() if request.periods else None
        
        # Calculate indicators
        ti = TechnicalIndicators(price_data, indicator_periods)
        df_with_indicators = ti.add_indicators()
        
        # Extract results into a structured response
        indicators_result = {}
        
        # Get the latest data
        latest_data = df_with_indicators.iloc[-1].to_dict()
        
        # Process and structure indicators by category
        try:
            # 1. Trend Indicators
            if "sma" in request.indicators or "trend" in request.indicators:
                trend_data = _extract_trend_indicators(df_with_indicators, latest_data, indicator_periods)
                indicators_result["trend"] = trend_data
                
            # 2. Momentum Indicators
            if any(ind in request.indicators for ind in ["rsi", "macd", "stochastic", "momentum"]):
                momentum_data = _extract_momentum_indicators(df_with_indicators, latest_data, indicator_periods)
                indicators_result["momentum"] = momentum_data
                
            # 3. Volatility Indicators
            if any(ind in request.indicators for ind in ["bollinger", "atr", "volatility"]):
                volatility_data = _extract_volatility_indicators(df_with_indicators, latest_data, indicator_periods)
                indicators_result["volatility"] = volatility_data
                
            # 4. Volume Indicators (if available)
            if "volume" in request.indicators and "volume" in price_data.columns:
                volume_data = _extract_volume_indicators(df_with_indicators, latest_data)
                indicators_result["volume"] = volume_data
                
            # 5. Ichimoku Cloud (if requested)
            if "ichimoku" in request.indicators:
                ichimoku_data = _extract_ichimoku_indicators(df_with_indicators, latest_data)
                indicators_result["ichimoku"] = ichimoku_data
                
            # 6. Oscillator Combined
            if "oscillator" in request.indicators or "momentum_composite" in df_with_indicators.columns:
                oscillator_data = _extract_oscillator_composite(df_with_indicators, latest_data)
                indicators_result["oscillator_composite"] = oscillator_data
                
            # 7. Market Analysis
            indicators_result["market_analysis"] = {
                "regime": market_regime,
                "overall_signal": latest_data.get("overall_signal", "neutral"),
                "buy_signals": int(latest_data.get("buy_signals", 0)),
                "sell_signals": int(latest_data.get("sell_signals", 0)),
                "neutral_signals": int(latest_data.get("neutral_signals", 0)),
                "buy_strength": float(latest_data.get("buy_strength", 0)),
                "sell_strength": float(latest_data.get("sell_strength", 0))
            }
            
            # 8. Market Condition
            # Prepare market analysis data
            if "adx" in df_with_indicators.columns:
                trend_strength = float(latest_data.get("adx", 0))
                is_trending = trend_strength > 25
                if "plus_di" in df_with_indicators.columns and "minus_di" in df_with_indicators.columns:
                    trend_direction = "bullish" if float(latest_data.get("plus_di", 0)) > float(latest_data.get("minus_di", 0)) else "bearish"
                else:
                    trend_direction = "unknown"
            else:
                trend_strength = 0
                is_trending = False
                trend_direction = "unknown"
            
            # Volatility assessment
            if "volatility_21d" in df_with_indicators.columns:
                volatility = float(latest_data.get("volatility_21d", 0))
                if volatility > 0.05:  # 5% daily volatility is very high for crypto
                    volatility_level = "very_high"
                elif volatility > 0.03:
                    volatility_level = "high"
                elif volatility > 0.015:
                    volatility_level = "medium"
                else:
                    volatility_level = "low"
            else:
                volatility = 0
                volatility_level = "unknown"
            
            indicators_result["market_condition"] = {
                "trend_strength": trend_strength,
                "trend_direction": trend_direction,
                "is_trending": is_trending,
                "volatility": volatility,
                "volatility_level": volatility_level,
                "market_regime": market_regime,
                "regime_description": _get_regime_description(market_regime)
            }
            
        except Exception as e:
            logger.error(f"Error extracting indicator data: {str(e)}")
            # Continue with partial results
        
        # Create response
        response = TechnicalIndicatorsResponse(
            project_id=request.project_id,
            indicators=indicators_result,
            latest_close=float(price_data['close'].iloc[-1]),
            market_regime=market_regime,
            latest_timestamp=price_data.index[-1],
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
    interval: str = Query("1d", description="Price data interval"),
    # custom_thresholds: Dict[str, float] = Query(None, description="Custom thresholds for event detection")
    pump_threshold: Optional[float] = Query(None, description="Threshold for pump events"),
    dump_threshold: Optional[float] = Query(None, description="Threshold for dump events"),
    volatility_threshold: Optional[float] = Query(None, description="Threshold for high volatility events"),
    volume_threshold: Optional[float] = Query(None, description="Threshold for volume spike events")
):
    logger.info(f"Market events request for {project_id}")
    
    try:
        # Get price data
        price_data = await get_price_data(
            project_id, 
            days=days, 
            interval=interval
        )
        
        # Detect market regime first
        market_regime = detect_market_regime(price_data)

        # custom_thresholds dari parameter individual
        custom_thresholds = {}
        if pump_threshold is not None:
            custom_thresholds['pump'] = pump_threshold
        if dump_threshold is not None:
            custom_thresholds['dump'] = dump_threshold
        if volatility_threshold is not None:
            custom_thresholds['volatility'] = volatility_threshold
        if volume_threshold is not None:
            custom_thresholds['volume_spike'] = volume_threshold
        
        # Adjust thresholds based on market regime if no custom thresholds
        if not custom_thresholds:
            if "volatile" in market_regime:
                # For volatile markets, use higher thresholds to avoid noise
                custom_thresholds = {
                    'pump': 2.5,        # Higher pump threshold
                    'dump': 2.5,        # Higher dump threshold
                    'volatility': 2.5,  # Higher volatility threshold
                    'volume_spike': 2.5 # Higher volume spike threshold
                }
            elif "trending" in market_regime:
                # For trending markets, use standard thresholds
                custom_thresholds = {
                    'pump': 2.0,        # Standard pump threshold
                    'dump': 2.0,        # Standard dump threshold
                    'volatility': 2.0,  # Standard volatility threshold
                    'volume_spike': 2.0 # Standard volume spike threshold
                }
            else:  # ranging
                # For ranging markets, use lower thresholds to detect smaller movements
                custom_thresholds = {
                    'pump': 1.8,        # Lower pump threshold
                    'dump': 1.8,        # Lower dump threshold
                    'volatility': 1.8,  # Lower volatility threshold
                    'volume_spike': 1.8 # Lower volume spike threshold
                }
        
        # Detect market events with adaptive thresholds
        events = detect_market_events(price_data, custom_thresholds=custom_thresholds)
        
        # Create response
        response = MarketEventResponse(
            project_id=project_id,
            latest_event=events.get('latest_event', 'normal'),
            market_regime=market_regime,
            event_counts=events.get('event_counts', {}),
            recent_events=events.get('recent_events', {}),
            close_price=float(price_data['close'].iloc[-1]),
            timestamp=price_data.index[-1]
        )
        
        return response
        
    except Exception as e:
        logger.error(f"Error detecting market events: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

# PERBAIKAN: Update price prediction endpoint untuk handling yang lebih baik
@router.get("/price-prediction/{project_id}", response_model=PricePredictionResponse)
async def predict_future_price(
    project_id: str = Path(..., description="Project ID"),
    days: int = Query(50, ge=1, le=365, description="Historical data days"),
    prediction_days: int = Query(7, ge=1, le=30, description="Days to predict"),
    interval: str = Query("1d", description="Price data interval"),
    model: str = Query("auto", description="Prediction model (auto, ml, arima, simple)")
):
    logger.info(f"Price prediction request for {project_id} using {model} model")
    
    # Periksa cache untuk menghindari komputasi berulang
    cache_key = f"price_prediction:{project_id}:{days}:{prediction_days}:{model}"
    if cache_key in _price_data_cache:
        cache_entry = _price_data_cache[cache_key]
        if datetime.now() < cache_entry['expires']:
            logger.info(f"Returning cached prediction for {project_id}")
            return cache_entry['data']
    
    try:
        start_time = time.time()
        
        # Get price data
        price_data = await get_price_data(
            project_id, 
            days=days, 
            interval=interval
        )
        
        # PERBAIKAN: Logging untuk debugging
        logger.info(f"Retrieved {len(price_data)} data points for {project_id}")
        
        # Detect market regime
        market_regime = detect_market_regime(price_data)
        logger.info(f"Market regime detected: {market_regime}")
        
        # PERBAIKAN: Enhanced model selection dengan timeout handling
        if model == "auto":
            if days >= 180 and len(price_data) >= 180:
                try:
                    import tensorflow as tf
                    model = "ml"
                    logger.info(f"Auto-selected ML model for {project_id} with {len(price_data)} data points")
                except ImportError:
                    model = "arima"
                    logger.info(f"TensorFlow not available, using ARIMA for {project_id}")
            elif len(price_data) >= 60:
                model = "arima"
                logger.info(f"Using ARIMA model for {project_id} with {len(price_data)} data points")
            else:
                model = "simple"
                logger.info(f"Using Simple model for {project_id} with {len(price_data)} data points")
        
        # PERBAIKAN: Enhanced prediction execution dengan timeout dan error handling
        import asyncio
        
        async def run_prediction_with_timeout():
            try:
                if model == "ml":
                    logger.info(f"Starting ML prediction for {project_id}...")
                    
                    # PERBAIKAN: Timeout yang lebih lama untuk training LSTM
                    result = await asyncio.wait_for(
                        asyncio.to_thread(predict_price_ml, price_data, prediction_days),
                        timeout=60.0  # Timeout diperpanjang menjadi 60 detik
                    )
                    
                    if result and 'error' not in result:
                        logger.info(f"ML prediction completed for {project_id}: {result.get('model_type', 'Unknown')}")
                        return result
                    else:
                        logger.warning(f"ML prediction failed for {project_id}, falling back to ARIMA")
                        return await asyncio.to_thread(predict_price_arima, price_data, days_to_predict=prediction_days)
                        
                elif model == "arima":
                    logger.info(f"Starting ARIMA prediction for {project_id}...")
                    result = await asyncio.to_thread(predict_price_arima, price_data, days_to_predict=prediction_days)
                    logger.info(f"ARIMA prediction completed for {project_id}: {result.get('model_type', 'Unknown')}")
                    return result
                else:
                    logger.info(f"Starting Simple prediction for {project_id}...")
                    result = await asyncio.to_thread(predict_price_simple, price_data, days_to_predict=prediction_days)
                    logger.info(f"Simple prediction completed for {project_id}: {result.get('model_type', 'Unknown')}")
                    return result
                    
            except asyncio.TimeoutError:
                logger.warning(f"Prediction timeout for {project_id}, falling back to simple model")
                return await asyncio.to_thread(predict_price_simple, price_data, days_to_predict=prediction_days)
            except Exception as e:
                logger.error(f"Error in prediction for {project_id}: {str(e)}")
                return await asyncio.to_thread(predict_price_simple, price_data, days_to_predict=prediction_days)
        
        # Jalankan prediksi dengan timeout handling
        prediction_result = await run_prediction_with_timeout()
        
        # PERBAIKAN: Enhanced fallback handling
        if prediction_result is None or 'error' in prediction_result:
            logger.warning(f"Prediction failed for {project_id}, using simple model fallback")
            prediction_result = predict_price_simple(price_data, days_to_predict=prediction_days)
        
        # PERBAIKAN: Validasi hasil prediksi
        if not prediction_result.get('prediction_data') or len(prediction_result.get('prediction_data', [])) == 0:
            logger.warning(f"Empty prediction data for {project_id}, regenerating with simple model")
            prediction_result = predict_price_simple(price_data, days_to_predict=prediction_days)
        
        # PERBAIKAN: Logging hasil prediksi untuk debugging
        logger.info(f"Final prediction for {project_id}: Model={prediction_result.get('model_type')}, Trend={prediction_result.get('trend')}, Confidence={prediction_result.get('confidence')}")
        
        # Get additional technical analysis
        ti = TechnicalIndicators(price_data)
        reversal_data = ti.generate_reversal_signals()
        trend_prediction = ti.predict_price_trend(periods=prediction_days)
        
        # PERBAIKAN: Enhanced prediction data formatting
        predictions = []
        if prediction_result.get('prediction_data'):
            for point in prediction_result.get('prediction_data', []):
                try:
                    # PERBAIKAN: Validate individual prediction points
                    date_str = point.get('date', '')
                    value = point.get('value', 0)
                    confidence = point.get('confidence', 0.5)
                    
                    # Ensure value is a valid number
                    if not isinstance(value, (int, float)) or not np.isfinite(value):
                        logger.warning(f"Invalid prediction value: {value}, skipping point")
                        continue
                        
                    predictions.append(PredictionDataPoint(
                        date=date_str,
                        value=float(value),
                        confidence=float(confidence)
                    ))
                except Exception as e:
                    logger.warning(f"Error formatting prediction point: {str(e)}")
                    continue
        
        # PERBAIKAN: Fallback jika tidak ada prediction data yang valid
        if not predictions:
            logger.warning(f"No valid predictions generated for {project_id}, creating fallback")
            current_price = float(price_data['close'].iloc[-1])
            dates = pd.date_range(
                start=price_data.index[-1] + pd.Timedelta(days=1),
                periods=prediction_days,
                freq='D'
            )
            
            for i, date in enumerate(dates):
                predictions.append(PredictionDataPoint(
                    date=date.strftime('%Y-%m-%d'),
                    value=current_price,  # Flat prediction as fallback
                    confidence=0.3
                ))
        
        # PERBAIHAN: Enhanced response creation dengan proper error handling
        try:
            current_price = prediction_result.get('current_price', float(price_data['close'].iloc[-1]))
            
            response = PricePredictionResponse(
                project_id=project_id,
                current_price=float(current_price),
                prediction_direction=prediction_result.get('trend', 'unknown'),
                predicted_change_percent=float(prediction_result.get('change_percent', 0.0)),
                confidence=float(prediction_result.get('confidence', 0.5)),
                model_type=prediction_result.get('model_type', 'Simple'),
                market_regime=market_regime,
                reversal_probability=reversal_data.get('reversal_probability'),
                support_levels={
                    "support_1": trend_prediction.get('support_1'),
                    "support_2": trend_prediction.get('support_2')
                },
                resistance_levels={
                    "resistance_1": trend_prediction.get('target_1'),
                    "resistance_2": trend_prediction.get('target_2')
                },
                predictions=predictions,
                timestamp=datetime.now()
            )
        except Exception as e:
            logger.error(f"Error creating response for {project_id}: {str(e)}")
            # Create minimal fallback response
            response = PricePredictionResponse(
                project_id=project_id,
                current_price=float(price_data['close'].iloc[-1]),
                prediction_direction='unknown',
                predicted_change_percent=0.0,
                confidence=0.3,
                model_type='Simple (Fallback)',
                market_regime=market_regime,
                reversal_probability=0.0,
                support_levels={},
                resistance_levels={},
                predictions=predictions if predictions else [],
                timestamp=datetime.now()
            )
        
        # Hitung waktu eksekusi
        execution_time = time.time() - start_time
        logger.info(f"Price prediction completed in {execution_time:.2f}s using {model} model")
        
        # PERBAIKAN: Cache dengan TTL yang disesuaikan berdasarkan model
        cache_ttl_minutes = 60 if model == "ml" else 30  # ML cache lebih lama
        _price_data_cache[cache_key] = {
            'data': response,
            'expires': datetime.now() + timedelta(minutes=cache_ttl_minutes)
        }
        
        return response
        
    except Exception as e:
        logger.error(f"Critical error generating price prediction for {project_id}: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        
        # PERBAIKAN: Return proper error response instead of raising exception
        try:
            current_price = float(price_data['close'].iloc[-1]) if 'price_data' in locals() else 100.0
            error_response = PricePredictionResponse(
                project_id=project_id,
                current_price=current_price,
                prediction_direction='unknown',
                predicted_change_percent=0.0,
                confidence=0.0,
                model_type='Error',
                market_regime='unknown',
                reversal_probability=0.0,
                support_levels={},
                resistance_levels={},
                predictions=[],
                timestamp=datetime.now()
            )
            return error_response
        except:
            # Final fallback - raise HTTP exception
            raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/alerts/{project_id}")
async def get_technical_alerts(
    project_id: str = Path(..., description="Project ID"),
    days: int = Query(30, ge=1, le=365),
    interval: str = Query("1d", description="Price data interval"),
    lookback: int = Query(5, ge=1, le=30, description="Number of periods to look back for alerts"),
    trading_style: str = Query("standard", description="Trading style (short_term, standard, long_term)")
):
    logger.info(f"Technical alerts request for {project_id}")
    
    try:
        # Get price data
        price_data = await get_price_data(
            project_id, 
            days=days, 
            interval=interval
        )
        
        # Detect market regime
        market_regime = detect_market_regime(price_data)
        
        # Get optimal parameters for this regime and trading style
        indicator_periods = get_optimal_parameters(price_data, market_regime, trading_style)
        
        # Calculate indicators and generate alerts
        ti = TechnicalIndicators(price_data, indicator_periods)
        
        # Perbaikan: Simpan dataframe dengan indikator
        df_with_indicators = ti.add_indicators()
        
        # Perbaikan: Gunakan df_with_indicators untuk generate_alerts
        alerts = ti.generate_alerts(lookback_period=lookback, df=df_with_indicators)
        
        # Get reversal signals
        reversal_data = ti.generate_reversal_signals()
        
        # Add market regime signals
        regime_alert = {
            'date': price_data.index[-1],
            'type': f"market_regime_{market_regime}",
            'message': f"Market Regime: {market_regime.replace('_', ' ')}",
            'signal': 'buy' if 'bullish' in market_regime else 'sell' if 'bearish' in market_regime else 'neutral',
            'strength': 0.6
        }
        alerts.insert(0, regime_alert)
        
        # Add reversal probability if significant
        if reversal_data.get('reversal_probability', 0) > 0.6:
            reversal_alert = {
                'date': price_data.index[-1],
                'type': f"potential_reversal_{reversal_data.get('direction', 'unknown')}",
                'message': f"Potential {reversal_data.get('direction', '')} reversal detected (probability: {reversal_data.get('reversal_probability', 0)*100:.1f}%)",
                'signal': 'buy' if reversal_data.get('direction') == 'bullish' else 'sell' if reversal_data.get('direction') == 'bearish' else 'neutral',
                'strength': reversal_data.get('reversal_probability', 0)
            }
            alerts.insert(1, reversal_alert)
        
        # Format response
        formatted_alerts = []
        for alert in alerts:
            formatted_alerts.append({
                "date": alert.get('date').isoformat() if isinstance(alert.get('date'), pd.Timestamp) else str(alert.get('date')),
                "type": alert.get('type'),
                "message": alert.get('message'),
                "signal": alert.get('signal'),
                "strength": alert.get('strength')
            })
        
        return {
            "project_id": project_id,
            "alerts": formatted_alerts,
            "count": len(formatted_alerts),
            "market_regime": market_regime,
            "period": f"{days} days ({interval})",
            "lookback": lookback,
            "trading_style": trading_style
        }
        
    except Exception as e:
        logger.error(f"Error generating technical alerts: {str(e)}")
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

# Backtesting endpoint
@router.post("/backtest/{project_id}")
async def backtest_strategy(
    project_id: str = Path(..., description="Project ID"),
    days: int = Query(180, ge=30, le=365, description="Historical days to backtest"),
    interval: str = Query("1d", description="Price data interval"),
    strategy_type: str = Query("macd", description="Strategy type (macd, rsi, bollinger)"),
    indicator_periods: Optional[Dict[str, Any]] = None,
    initial_capital: float = Query(10000.0, gt=0, description="Initial capital for backtest")
):
    """
    Backtest a trading strategy on historical data
    """
    logger.info(f"Backtest request for {project_id} using {strategy_type} strategy")
    
    try:
        # Get historical price data
        price_data = await get_price_data(
            project_id,
            days=days,
            interval=interval
        )

        # Detect market regime
        market_regime = detect_market_regime(price_data)
        
        # Get optimal parameters if none provided
        if not indicator_periods:
            indicator_periods = get_optimal_parameters(price_data, market_regime)
            logger.info(f"Using optimized parameters for {market_regime} regime")
            
        # Run backtest - import dari src.technical.signals untuk menghindari rekursi
        from src.technical.signals import backtest_strategy as run_backtest
        backtest_results = run_backtest(
            price_data, 
            strategy_type=strategy_type,
            indicator_periods=indicator_periods,
            initial_capital=initial_capital
        )
        
        # Format trade history
        trade_history = []
        for trade in backtest_results.get('trades', []):
            trade_history.append({
                "date": trade['date'].isoformat() if isinstance(trade['date'], pd.Timestamp) else str(trade['date']),
                "type": trade['type'],
                "price": float(trade['price']),
                "holdings": float(trade['holdings']),
                "capital": float(trade['capital'])
            })
            
        # Calculate additional metrics
        returns_series = backtest_results.get('returns_series', pd.Series())
        drawdown_series = backtest_results.get('drawdown_series', pd.Series())
        
        # Helper functions untuk metrik tambahan
        def calculate_sortino_ratio(returns):
            """Calculate Sortino ratio (return / downside risk)"""
            if returns.empty:
                return 0
            downside_returns = returns[returns < 0]
            if downside_returns.empty or downside_returns.std() == 0:
                return 0
            return (returns.mean() * np.sqrt(252)) / (downside_returns.std() * np.sqrt(252))
        
        def get_max_consecutive(trades, result_type):
            """Get maximum consecutive wins or losses"""
            max_consecutive = 0
            current_consecutive = 0
            
            if result_type == "win":
                for i in range(0, len(trades), 2):
                    if i+1 < len(trades) and trades[i+1]['capital'] > trades[i]['capital']:
                        current_consecutive += 1
                        max_consecutive = max(max_consecutive, current_consecutive)
                    else:
                        current_consecutive = 0
            else:  # loss
                for i in range(0, len(trades), 2):
                    if i+1 < len(trades) and trades[i+1]['capital'] <= trades[i]['capital']:
                        current_consecutive += 1
                        max_consecutive = max(max_consecutive, current_consecutive)
                    else:
                        current_consecutive = 0
                        
            return max_consecutive
        
        def calculate_profit_factor(trades):
            """Calculate profit factor (total gains / total losses)"""
            total_gains = 0
            total_losses = 0
            
            for i in range(0, len(trades), 2):
                if i+1 < len(trades):
                    trade_result = trades[i+1]['capital'] - trades[i]['capital']
                    if trade_result > 0:
                        total_gains += trade_result
                    else:
                        total_losses += abs(trade_result)
            
            return total_gains / total_losses if total_losses > 0 else float('inf')
        
        def calculate_recovery_factor(total_return, max_drawdown):
            """Calculate recovery factor (return / max drawdown)"""
            if max_drawdown == 0:
                return float('inf')
            return total_return / max_drawdown
        
        additional_metrics = {
            "volatility": float(returns_series.std() * np.sqrt(252)) if not returns_series.empty else 0,
            "sortino_ratio": calculate_sortino_ratio(returns_series),
            "max_consecutive_wins": get_max_consecutive(trade_history, "win"),
            "max_consecutive_losses": get_max_consecutive(trade_history, "loss"),
            "profit_factor": calculate_profit_factor(trade_history),
            "recovery_factor": calculate_recovery_factor(
                backtest_results['total_return'],
                backtest_results['max_drawdown']
            ) if backtest_results.get('max_drawdown') else 0
        }

        # Create response
        response = {
            "project_id": project_id,
            "strategy": strategy_type,
            "market_regime": market_regime,
            "period": f"{days} days ({interval})",
            "initial_capital": initial_capital,
            "final_capital": initial_capital * (1 + backtest_results['total_return']),
            "total_return": backtest_results['total_return'],
            "annual_return": backtest_results['annual_return'],
            "max_drawdown": backtest_results['max_drawdown'],
            "sharpe_ratio": backtest_results['sharpe_ratio'],
            "win_rate": backtest_results['win_rate'],
            "num_trades": backtest_results['num_trades'],
            "trades": trade_history,
            "additional_metrics": additional_metrics,
            "parameters_used": indicator_periods,
            "timestamp": datetime.now().isoformat()
        }
        
        return response
        
    except Exception as e:
        logger.error(f"Error running backtest: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")