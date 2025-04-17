"""
Modul untuk menghasilkan sinyal trading berdasarkan analisis teknikal
"""

import os
import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple, Any, Union
import time
from datetime import datetime
from pathlib import Path

# Import TA-Lib jika tersedia
try:
    import talib
    TALIB_AVAILABLE = True
except ImportError:
    TALIB_AVAILABLE = False
    logging.warning("TA-Lib not available, using pandas-based indicators instead")

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import TRADING_SIGNAL_WINDOW, CONFIDENCE_THRESHOLD

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


def calculate_rsi(prices: pd.Series, window: int = 14) -> pd.Series:
    """
    Calculate Relative Strength Index (RSI)
    
    Args:
        prices: Series of prices
        window: Window size for RSI calculation
        
    Returns:
        pd.Series: RSI values
    """
    if TALIB_AVAILABLE:
        return pd.Series(talib.RSI(prices.values, timeperiod=window), index=prices.index)
    else:
        # Calculate RSI using pandas
        delta = prices.diff()
        gain = delta.where(delta > 0, 0)
        loss = -delta.where(delta < 0, 0)
        
        avg_gain = gain.rolling(window=window).mean()
        avg_loss = loss.rolling(window=window).mean()
        
        rs = avg_gain / avg_loss.replace(0, np.finfo(float).eps)  # Avoid division by zero
        rsi = 100 - (100 / (1 + rs))
        
        return rsi


def calculate_macd(prices: pd.Series, 
                  fast_period: int = 12, 
                  slow_period: int = 26, 
                  signal_period: int = 9) -> Tuple[pd.Series, pd.Series, pd.Series]:
    """
    Calculate Moving Average Convergence Divergence (MACD)
    
    Args:
        prices: Series of prices
        fast_period: Fast EMA period
        slow_period: Slow EMA period
        signal_period: Signal line period
        
    Returns:
        tuple: (macd, signal, histogram)
    """
    if TALIB_AVAILABLE:
        macd, signal, hist = talib.MACD(
            prices.values, 
            fastperiod=fast_period, 
            slowperiod=slow_period, 
            signalperiod=signal_period
        )
        return (pd.Series(macd, index=prices.index),
                pd.Series(signal, index=prices.index),
                pd.Series(hist, index=prices.index))
    else:
        # Calculate MACD using pandas
        fast_ema = prices.ewm(span=fast_period, adjust=False).mean()
        slow_ema = prices.ewm(span=slow_period, adjust=False).mean()
        
        macd = fast_ema - slow_ema
        signal = macd.ewm(span=signal_period, adjust=False).mean()
        histogram = macd - signal
        
        return macd, signal, histogram


def calculate_bollinger_bands(prices: pd.Series, window: int = 20, num_std: float = 2.0) -> Tuple[pd.Series, pd.Series, pd.Series]:
    """
    Calculate Bollinger Bands
    
    Args:
        prices: Series of prices
        window: Window size for moving average
        num_std: Number of standard deviations for bands
        
    Returns:
        tuple: (upper_band, middle_band, lower_band)
    """
    if TALIB_AVAILABLE:
        upper, middle, lower = talib.BBANDS(
            prices.values, 
            timeperiod=window, 
            nbdevup=num_std, 
            nbdevdn=num_std, 
            matype=0
        )
        return (pd.Series(upper, index=prices.index),
                pd.Series(middle, index=prices.index),
                pd.Series(lower, index=prices.index))
    else:
        # Calculate Bollinger Bands using pandas
        middle = prices.rolling(window=window).mean()
        std = prices.rolling(window=window).std()
        
        upper = middle + (std * num_std)
        lower = middle - (std * num_std)
        
        return upper, middle, lower


def calculate_stochastic(prices: pd.Series, 
                        high_prices: pd.Series, 
                        low_prices: pd.Series,
                        k_period: int = 14, 
                        d_period: int = 3) -> Tuple[pd.Series, pd.Series]:
    """
    Calculate Stochastic Oscillator
    
    Args:
        prices: Series of closing prices
        high_prices: Series of high prices
        low_prices: Series of low prices
        k_period: K period
        d_period: D period
        
    Returns:
        tuple: (k, d)
    """
    if TALIB_AVAILABLE:
        k, d = talib.STOCH(
            high_prices.values, 
            low_prices.values, 
            prices.values, 
            fastk_period=k_period, 
            slowk_period=d_period, 
            slowk_matype=0, 
            slowd_period=d_period, 
            slowd_matype=0
        )
        return pd.Series(k, index=prices.index), pd.Series(d, index=prices.index)
    else:
        # Calculate Stochastic Oscillator using pandas
        lowest_low = low_prices.rolling(window=k_period).min()
        highest_high = high_prices.rolling(window=k_period).max()
        
        k = 100 * (prices - lowest_low) / (highest_high - lowest_low).replace(0, np.finfo(float).eps)
        d = k.rolling(window=d_period).mean()
        
        return k, d


def calculate_atr(high_prices: pd.Series, 
                low_prices: pd.Series, 
                close_prices: pd.Series, 
                window: int = 14) -> pd.Series:
    """
    Calculate Average True Range (ATR)
    
    Args:
        high_prices: Series of high prices
        low_prices: Series of low prices
        close_prices: Series of closing prices
        window: Window size for ATR calculation
        
    Returns:
        pd.Series: ATR values
    """
    if TALIB_AVAILABLE:
        atr = talib.ATR(
            high_prices.values, 
            low_prices.values, 
            close_prices.values, 
            timeperiod=window
        )
        return pd.Series(atr, index=close_prices.index)
    else:
        # Calculate ATR using pandas
        prev_close = close_prices.shift(1)
        tr1 = high_prices - low_prices  # High - Low
        tr2 = (high_prices - prev_close).abs()  # |High - Previous Close|
        tr3 = (low_prices - prev_close).abs()  # |Low - Previous Close|
        
        true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
        atr = true_range.rolling(window=window).mean()
        
        return atr


def calculate_adx(high_prices: pd.Series, 
                low_prices: pd.Series, 
                close_prices: pd.Series, 
                window: int = 14) -> Tuple[pd.Series, pd.Series, pd.Series]:
    """
    Calculate Average Directional Index (ADX)
    
    Args:
        high_prices: Series of high prices
        low_prices: Series of low prices
        close_prices: Series of closing prices
        window: Window size for ADX calculation
        
    Returns:
        tuple: (adx, plus_di, minus_di)
    """
    if TALIB_AVAILABLE:
        adx = talib.ADX(
            high_prices.values, 
            low_prices.values, 
            close_prices.values, 
            timeperiod=window
        )
        plus_di = talib.PLUS_DI(
            high_prices.values, 
            low_prices.values, 
            close_prices.values, 
            timeperiod=window
        )
        minus_di = talib.MINUS_DI(
            high_prices.values, 
            low_prices.values, 
            close_prices.values, 
            timeperiod=window
        )
        return (pd.Series(adx, index=close_prices.index),
                pd.Series(plus_di, index=close_prices.index),
                pd.Series(minus_di, index=close_prices.index))
    else:
        # Calculate ADX using pandas (simplified implementation)
        # This is a simplified version and doesn't exactly match TA-Lib
        
        # 1. True Range
        prev_close = close_prices.shift(1)
        tr1 = high_prices - low_prices  # High - Low
        tr2 = (high_prices - prev_close).abs()  # |High - Previous Close|
        tr3 = (low_prices - prev_close).abs()  # |Low - Previous Close|
        
        true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
        atr = true_range.rolling(window=window).mean()
        
        # 2. Directional Movement
        high_diff = high_prices - high_prices.shift(1)
        low_diff = low_prices.shift(1) - low_prices
        
        plus_dm = high_diff.where((high_diff > low_diff) & (high_diff > 0), 0)
        minus_dm = low_diff.where((low_diff > high_diff) & (low_diff > 0), 0)
        
        # 3. Directional Indicators
        plus_di = 100 * (plus_dm.rolling(window=window).mean() / atr)
        minus_di = 100 * (minus_dm.rolling(window=window).mean() / atr)
        
        # 4. Directional Index
        dx = 100 * ((plus_di - minus_di).abs() / (plus_di + minus_di).replace(0, np.finfo(float).eps))
        
        # 5. Average Directional Index
        adx = dx.rolling(window=window).mean()
        
        return adx, plus_di, minus_di


def generate_trading_signals(prices_df: pd.DataFrame, window: int = TRADING_SIGNAL_WINDOW) -> Dict[str, Any]:
    """
    Generate trading signals based on technical indicators
    
    Args:
        prices_df: DataFrame with price data (must have 'close' column, 
                  optionally 'high', 'low', 'volume')
        window: Window size for indicators
        
    Returns:
        dict: Trading signals and analysis
    """
    logger.info("Generating trading signals")
    
    # Verify required columns
    required_columns = ['close']
    for col in required_columns:
        if col not in prices_df.columns:
            logger.error(f"Required column '{col}' not found in prices_df")
            return {
                "error": f"Missing required column: {col}",
                "action": "hold",
                "confidence": 0.0
            }
    
    # Use appropriate column names or defaults
    close_col = 'close'
    high_col = 'high' if 'high' in prices_df.columns else close_col
    low_col = 'low' if 'low' in prices_df.columns else close_col
    
    # Extract price series
    close_prices = prices_df[close_col]
    high_prices = prices_df[high_col]
    low_prices = prices_df[low_col]
    
    # Calculate indicators
    # 1. RSI
    rsi = calculate_rsi(close_prices, window=14)
    
    # 2. MACD
    macd, signal, histogram = calculate_macd(close_prices)
    
    # 3. Bollinger Bands
    upper_band, middle_band, lower_band = calculate_bollinger_bands(close_prices, window=20)
    
    # 4. Stochastic Oscillator
    k, d = calculate_stochastic(close_prices, high_prices, low_prices)
    
    # 5. ADX
    adx, plus_di, minus_di = calculate_adx(high_prices, low_prices, close_prices)
    
    # 6. Moving Averages
    sma_20 = close_prices.rolling(window=20).mean()
    sma_50 = close_prices.rolling(window=50).mean()
    sma_200 = close_prices.rolling(window=200).mean()
    
    # Get latest values
    latest_close = close_prices.iloc[-1]
    latest_rsi = rsi.iloc[-1]
    latest_macd = macd.iloc[-1]
    latest_signal = signal.iloc[-1]
    latest_histogram = histogram.iloc[-1]
    latest_upper = upper_band.iloc[-1]
    latest_middle = middle_band.iloc[-1]
    latest_lower = lower_band.iloc[-1]
    latest_k = k.iloc[-1]
    latest_d = d.iloc[-1]
    latest_adx = adx.iloc[-1]
    latest_plus_di = plus_di.iloc[-1]
    latest_minus_di = minus_di.iloc[-1]
    latest_sma_20 = sma_20.iloc[-1]
    latest_sma_50 = sma_50.iloc[-1]
    latest_sma_200 = sma_200.iloc[-1]
    
    # Calculate signals
    signals = {}
    
    # RSI signals
    if latest_rsi < 30:
        signals['rsi'] = {
            'signal': 'buy',
            'strength': min(1.0, (30 - latest_rsi) / 10),
            'description': f"RSI is oversold at {latest_rsi:.2f}"
        }
    elif latest_rsi > 70:
        signals['rsi'] = {
            'signal': 'sell',
            'strength': min(1.0, (latest_rsi - 70) / 10),
            'description': f"RSI is overbought at {latest_rsi:.2f}"
        }
    else:
        signals['rsi'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"RSI is neutral at {latest_rsi:.2f}"
        }
    
    # MACD signals
    macd_cross_up = (macd.iloc[-1] > signal.iloc[-1]) and (macd.iloc[-2] <= signal.iloc[-2])
    macd_cross_down = (macd.iloc[-1] < signal.iloc[-1]) and (macd.iloc[-2] >= signal.iloc[-2])
    
    if macd_cross_up:
        signals['macd'] = {
            'signal': 'buy',
            'strength': 0.8,
            'description': "MACD crossed above signal line (bullish)"
        }
    elif macd_cross_down:
        signals['macd'] = {
            'signal': 'sell',
            'strength': 0.8,
            'description': "MACD crossed below signal line (bearish)"
        }
    elif latest_macd > latest_signal:
        signals['macd'] = {
            'signal': 'buy',
            'strength': 0.6,
            'description': "MACD is above signal line (bullish)"
        }
    elif latest_macd < latest_signal:
        signals['macd'] = {
            'signal': 'sell',
            'strength': 0.6,
            'description': "MACD is below signal line (bearish)"
        }
    else:
        signals['macd'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': "MACD is neutral"
        }
    
    # Bollinger Bands signals
    bb_percent = (latest_close - latest_lower) / (latest_upper - latest_lower)
    
    if latest_close > latest_upper:
        signals['bollinger'] = {
            'signal': 'sell',
            'strength': 0.7,
            'description': "Price above upper Bollinger Band (overbought)"
        }
    elif latest_close < latest_lower:
        signals['bollinger'] = {
            'signal': 'buy',
            'strength': 0.7,
            'description': "Price below lower Bollinger Band (oversold)"
        }
    elif bb_percent > 0.8:
        signals['bollinger'] = {
            'signal': 'sell',
            'strength': 0.6,
            'description': "Price near upper Bollinger Band (potential reversal)"
        }
    elif bb_percent < 0.2:
        signals['bollinger'] = {
            'signal': 'buy',
            'strength': 0.6,
            'description': "Price near lower Bollinger Band (potential reversal)"
        }
    else:
        signals['bollinger'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': "Price within Bollinger Bands (neutral)"
        }
    
    # Stochastic signals
    stoch_cross_up = (k.iloc[-1] > d.iloc[-1]) and (k.iloc[-2] <= d.iloc[-2])
    stoch_cross_down = (k.iloc[-1] < d.iloc[-1]) and (k.iloc[-2] >= d.iloc[-2])
    
    if latest_k < 20 and stoch_cross_up:
        signals['stochastic'] = {
            'signal': 'buy',
            'strength': 0.8,
            'description': "Stochastic %K crossed above %D in oversold region (strong buy)"
        }
    elif latest_k > 80 and stoch_cross_down:
        signals['stochastic'] = {
            'signal': 'sell',
            'strength': 0.8,
            'description': "Stochastic %K crossed below %D in overbought region (strong sell)"
        }
    elif latest_k < 20:
        signals['stochastic'] = {
            'signal': 'buy',
            'strength': 0.7,
            'description': f"Stochastic oscillator is oversold at {latest_k:.2f}"
        }
    elif latest_k > 80:
        signals['stochastic'] = {
            'signal': 'sell',
            'strength': 0.7,
            'description': f"Stochastic oscillator is overbought at {latest_k:.2f}"
        }
    else:
        signals['stochastic'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': "Stochastic oscillator is neutral"
        }
    
    # ADX signals
    if latest_adx > 25:
        if latest_plus_di > latest_minus_di:
            signals['adx'] = {
                'signal': 'buy',
                'strength': min(1.0, latest_adx / 50),
                'description': f"Strong trend with +DI > -DI (ADX: {latest_adx:.2f})"
            }
        else:
            signals['adx'] = {
                'signal': 'sell',
                'strength': min(1.0, latest_adx / 50),
                'description': f"Strong trend with -DI > +DI (ADX: {latest_adx:.2f})"
            }
    else:
        signals['adx'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"Weak trend (ADX: {latest_adx:.2f})"
        }
    
    # Moving Average signals
    golden_cross = (sma_50.iloc[-1] > sma_200.iloc[-1]) and (sma_50.iloc[-2] <= sma_200.iloc[-2])
    death_cross = (sma_50.iloc[-1] < sma_200.iloc[-1]) and (sma_50.iloc[-2] >= sma_200.iloc[-2])
    
    if golden_cross:
        signals['moving_avg'] = {
            'signal': 'buy',
            'strength': 0.9,
            'description': "Golden Cross: 50-day MA crossed above 200-day MA (strong buy)"
        }
    elif death_cross:
        signals['moving_avg'] = {
            'signal': 'sell',
            'strength': 0.9,
            'description': "Death Cross: 50-day MA crossed below 200-day MA (strong sell)"
        }
    elif latest_close > latest_sma_20 and latest_sma_20 > latest_sma_50 and latest_sma_50 > latest_sma_200:
        signals['moving_avg'] = {
            'signal': 'buy',
            'strength': 0.8,
            'description': "Price above all major moving averages (bullish trend)"
        }
    elif latest_close < latest_sma_20 and latest_sma_20 < latest_sma_50 and latest_sma_50 < latest_sma_200:
        signals['moving_avg'] = {
            'signal': 'sell',
            'strength': 0.8,
            'description': "Price below all major moving averages (bearish trend)"
        }
    elif latest_close > latest_sma_200:
        signals['moving_avg'] = {
            'signal': 'buy',
            'strength': 0.6,
            'description': "Price above 200-day MA (long-term bullish)"
        }
    elif latest_close < latest_sma_200:
        signals['moving_avg'] = {
            'signal': 'sell',
            'strength': 0.6,
            'description': "Price below 200-day MA (long-term bearish)"
        }
    else:
        signals['moving_avg'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': "Moving averages are neutral"
        }
    
    # Combine signals
    buy_count = sum(1 for s in signals.values() if s['signal'] == 'buy')
    sell_count = sum(1 for s in signals.values() if s['signal'] == 'sell')
    hold_count = sum(1 for s in signals.values() if s['signal'] == 'hold')
    
    buy_strength = sum(s['strength'] for s in signals.values() if s['signal'] == 'buy')
    sell_strength = sum(s['strength'] for s in signals.values() if s['signal'] == 'sell')
    
    total_indicators = len(signals)
    
    # Determine overall action
    if buy_count > sell_count and buy_strength > sell_strength:
        action = "buy"
        confidence = buy_strength / (buy_strength + sell_strength + 0.001)
    elif sell_count > buy_count and sell_strength > buy_strength:
        action = "sell"
        confidence = sell_strength / (buy_strength + sell_strength + 0.001)
    else:
        action = "hold"
        confidence = max(0.5, hold_count / total_indicators)
    
    # Normalize confidence to range [0, 1]
    confidence = min(1.0, max(0.0, confidence))
    
    # Create supporting evidence
    evidence = []
    
    for indicator, signal in signals.items():
        if signal['signal'] == action or (action == "hold" and signal['signal'] == "hold"):
            evidence.append(signal['description'])
    
    # Generate target price if applicable
    target_price = None
    
    if 'volume' in prices_df.columns:
        atr = calculate_atr(high_prices, low_prices, close_prices)
        latest_atr = atr.iloc[-1]
        
        if action == "buy":
            # Target price for buying: current price + 2*ATR
            target_price = latest_close + (2 * latest_atr)
        elif action == "sell":
            # Target price for selling: current price - 2*ATR
            target_price = latest_close - (2 * latest_atr)
    
    # Check if confidence meets threshold
    strong_signal = confidence >= CONFIDENCE_THRESHOLD
    
    # Create final recommendation
    recommendation = {
        "action": action,
        "confidence": float(confidence),
        "strong_signal": strong_signal,
        "evidence": evidence,
        "indicators": {
            "rsi": float(latest_rsi),
            "macd": float(latest_macd),
            "macd_signal": float(latest_signal),
            "macd_histogram": float(latest_histogram),
            "bollinger_percent": float(bb_percent),
            "stochastic_k": float(latest_k),
            "stochastic_d": float(latest_d),
            "adx": float(latest_adx)
        }
    }
    
    if target_price is not None:
        recommendation["target_price"] = float(target_price)
    
    return recommendation


def personalize_signals(signals: Dict[str, Any], 
                      risk_tolerance: str = 'medium') -> Dict[str, Any]:
    """
    Personalize trading signals based on user risk tolerance
    
    Args:
        signals: Trading signals from generate_trading_signals
        risk_tolerance: User risk tolerance ('low', 'medium', 'high')
        
    Returns:
        dict: Personalized trading signals
    """
    # Create a copy to avoid modifying the original
    personalized = signals.copy()
    
    # Default confidence threshold for different risk profiles
    thresholds = {
        'low': 0.8,    # Conservative, needs strong signals
        'medium': 0.6,  # Balanced
        'high': 0.4     # Aggressive, acts on weaker signals
    }
    
    # Apply risk tolerance to confidence
    confidence = signals.get('confidence', 0.5)
    action = signals.get('action', 'hold')
    
    # Adjust recommendation based on risk tolerance
    if risk_tolerance == 'low':
        # Conservative: Higher bar for buy/sell actions
        if confidence < thresholds['low']:
            personalized['action'] = 'hold'
            personalized['personalized_message'] = "Signal not strong enough for your conservative risk profile"
        else:
            personalized['personalized_message'] = "Strong signal matches your conservative risk profile"
            
    elif risk_tolerance == 'high':
        # Aggressive: Amplify confidence for buy/sell
        if action != 'hold':
            boosted_confidence = min(1.0, confidence * 1.2)
            personalized['confidence'] = boosted_confidence
            personalized['personalized_message'] = "Signal amplified for your aggressive risk profile"
        else:
            # Convert weak buy/sell signals to action for aggressive profiles
            buy_signals = sum(1 for s in signals.get('evidence', []) if 'buy' in s.lower())
            sell_signals = sum(1 for s in signals.get('evidence', []) if 'sell' in s.lower())
            
            if buy_signals > sell_signals and buy_signals > 1:
                personalized['action'] = 'buy'
                personalized['confidence'] = 0.5
                personalized['personalized_message'] = "Converted to buy signal for your aggressive risk profile"
            elif sell_signals > buy_signals and sell_signals > 1:
                personalized['action'] = 'sell'
                personalized['confidence'] = 0.5
                personalized['personalized_message'] = "Converted to sell signal for your aggressive risk profile"
            else:
                # TAMBAHKAN BARIS INI:
                personalized['personalized_message'] = "Holding despite your aggressive risk profile due to unclear signals"
                
    else:  # medium
        personalized['personalized_message'] = "Signal matches your balanced risk profile"
    
    # Add risk profile info
    personalized['risk_profile'] = risk_tolerance
    
    return personalized


def detect_market_events(prices_df: pd.DataFrame, 
                        window: int = 20, 
                        threshold: float = 2.0) -> Dict[str, Any]:
    """
    Detect market events such as pumps, dumps, high volatility, etc.
    
    Args:
        prices_df: DataFrame with price data
        window: Window size for calculations
        threshold: Standard deviation threshold for event detection
        
    Returns:
        dict: Detected market events
    """
    # Verify required columns
    required_columns = ['close']
    for col in required_columns:
        if col not in prices_df.columns:
            logger.error(f"Required column '{col}' not found in prices_df")
            return {"error": f"Missing required column: {col}"}
    
    # Use appropriate column names or defaults
    close_col = 'close'
    
    # Extract price series
    close_prices = prices_df[close_col]
    
    # Calculate returns
    returns = close_prices.pct_change()
    
    # Calculate moving average and standard deviation
    ma = close_prices.rolling(window=window).mean()
    std = close_prices.rolling(window=window).std()
    
    # Calculate upper and lower bounds
    upper_bound = ma + (threshold * std)
    lower_bound = ma - (threshold * std)
    
    # Detect events
    events = {
        "pump": close_prices > upper_bound,
        "dump": close_prices < lower_bound,
        "high_volatility": returns.abs() > (returns.std() * threshold)
    }
    
    # Count events
    event_counts = {
        "pump": events["pump"].sum(),
        "dump": events["dump"].sum(),
        "high_volatility": events["high_volatility"].sum()
    }
    
    # Get latest event (if any)
    latest_event = "normal"
    
    if events["pump"].iloc[-1]:
        latest_event = "pump"
    elif events["dump"].iloc[-1]:
        latest_event = "dump"
    elif events["high_volatility"].iloc[-1]:
        latest_event = "high_volatility"
    
    return {
        "event_counts": event_counts,
        "latest_event": latest_event,
        "events": events
    }


if __name__ == "__main__":
    # Test the module with demo data
    # Create sample price data
    rng = np.random.default_rng(42)  # Create Generator instance with seed
    n = 200
    dates = pd.date_range(end=datetime.now(), periods=n)
    
    # Generate synthetic price data using rng methods
    close = 100 + np.cumsum(rng.normal(0, 1, n))
    high = close + rng.uniform(0, 3, n)
    low = close - rng.uniform(0, 3, n)
    volume = rng.uniform(1000, 5000, n)
    
    # Create DataFrame
    df = pd.DataFrame({
        'date': dates,
        'close': close,
        'high': high,
        'low': low,
        'volume': volume
    }).set_index('date')
    
    # Test signal generation
    signals = generate_trading_signals(df)
    
    # Print results
    print("\n=== Technical Analysis Signals ===")
    print(f"Action: {signals['action'].upper()}")
    print(f"Confidence: {signals['confidence']:.2f}")
    
    if 'target_price' in signals:
        print(f"Target Price: ${signals['target_price']:.2f}")
        
    print("\nEvidence:")
    for evidence in signals['evidence']:
        print(f"- {evidence}")
        
    print("\nKey Indicators:")
    for indicator, value in signals['indicators'].items():
        print(f"- {indicator}: {value:.2f}")
        
    # Test personalization
    personalized = personalize_signals(signals, risk_tolerance='high')
    
    print("\n=== Personalized Signals (High Risk) ===")
    print(f"Action: {personalized['action'].upper()}")
    print(f"Confidence: {personalized['confidence']:.2f}")
    print(f"Message: {personalized['personalized_message']}")
    
    # Test event detection
    events = detect_market_events(df)
    
    print("\n=== Market Events ===")
    print(f"Latest Event: {events['latest_event']}")
    print("Event Counts:")
    for event, count in events['event_counts'].items():
        print(f"- {event}: {count}")