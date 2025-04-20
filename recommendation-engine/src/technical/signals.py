"""
Modul untuk menghasilkan sinyal trading berdasarkan analisis teknikal (VERSI YANG DIPERBARUI)
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
    logger = logging.getLogger(__name__)
    logger.info("TA-Lib berhasil diimpor dan tersedia untuk penggunaan")
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
    # Pastikan ada cukup data untuk perhitungan
    if len(prices) < window * 2:
        logger.warning(f"Tidak cukup data untuk menghitung RSI. Minimal {window * 2} titik data diperlukan.")
        return pd.Series(np.nan, index=prices.index)
        
    if TALIB_AVAILABLE:
        try:
            # Pastikan input adalah array numpy yang valid
            if np.isnan(prices.values).any():
                prices_cleaned = prices.fillna(method='ffill').fillna(method='bfill')
                rsi = pd.Series(talib.RSI(prices_cleaned.values, timeperiod=window), index=prices.index)
            else:
                rsi = pd.Series(talib.RSI(prices.values, timeperiod=window), index=prices.index)
                
            # Jika masih ada NaN, coba metode pandas sebagai fallback
            if np.isnan(rsi.values).all():
                logger.warning("TA-Lib RSI mengembalikan semua NaN, menggunakan implementasi pandas sebagai fallback")
                return _calculate_rsi_pandas(prices, window)
                
            return rsi
        except Exception as e:
            logger.error(f"Error saat menghitung RSI dengan TA-Lib: {str(e)}")
            return _calculate_rsi_pandas(prices, window)
    else:
        return _calculate_rsi_pandas(prices, window)


def _calculate_rsi_pandas(prices: pd.Series, window: int = 14) -> pd.Series:
    """Implementasi RSI menggunakan pandas"""
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
    min_periods_needed = slow_period + signal_period + 5  # Tambahan 5 untuk margin keamanan
    
    # Pastikan ada cukup data untuk perhitungan
    if len(prices) < min_periods_needed:
        logger.warning(f"Tidak cukup data untuk menghitung MACD dengan benar. Minimal {min_periods_needed} titik data diperlukan.")
        empty_series = pd.Series(np.nan, index=prices.index)
        return empty_series, empty_series, empty_series
    
    if TALIB_AVAILABLE:
        try:
            # Pastikan input adalah array numpy yang valid
            if np.isnan(prices.values).any():
                prices_cleaned = prices.fillna(method='ffill').fillna(method='bfill')
                macd, signal, hist = talib.MACD(
                    prices_cleaned.values, 
                    fastperiod=fast_period, 
                    slowperiod=slow_period, 
                    signalperiod=signal_period
                )
            else:
                macd, signal, hist = talib.MACD(
                    prices.values, 
                    fastperiod=fast_period, 
                    slowperiod=slow_period, 
                    signalperiod=signal_period
                )
                
            # Cek hasilnya valid
            if np.isnan(macd).all() or np.isnan(signal).all():
                logger.warning("TA-Lib MACD mengembalikan semua NaN, menggunakan implementasi pandas sebagai fallback")
                return _calculate_macd_pandas(prices, fast_period, slow_period, signal_period)
                
            return (pd.Series(macd, index=prices.index),
                    pd.Series(signal, index=prices.index),
                    pd.Series(hist, index=prices.index))
        except Exception as e:
            logger.error(f"Error saat menghitung MACD dengan TA-Lib: {str(e)}")
            return _calculate_macd_pandas(prices, fast_period, slow_period, signal_period)
    else:
        return _calculate_macd_pandas(prices, fast_period, slow_period, signal_period)


def _calculate_macd_pandas(prices: pd.Series, 
                          fast_period: int = 12, 
                          slow_period: int = 26, 
                          signal_period: int = 9) -> Tuple[pd.Series, pd.Series, pd.Series]:
    """Implementasi MACD menggunakan pandas"""
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
    # Pastikan ada cukup data untuk perhitungan
    if len(prices) < window:
        logger.warning(f"Tidak cukup data untuk menghitung Bollinger Bands. Minimal {window} titik data diperlukan.")
        empty_series = pd.Series(np.nan, index=prices.index)
        return empty_series, empty_series, empty_series
        
    if TALIB_AVAILABLE:
        try:
            # Pastikan input adalah array numpy yang valid
            if np.isnan(prices.values).any():
                prices_cleaned = prices.fillna(method='ffill').fillna(method='bfill')
                upper, middle, lower = talib.BBANDS(
                    prices_cleaned.values, 
                    timeperiod=window, 
                    nbdevup=num_std, 
                    nbdevdn=num_std, 
                    matype=0
                )
            else:
                upper, middle, lower = talib.BBANDS(
                    prices.values, 
                    timeperiod=window, 
                    nbdevup=num_std, 
                    nbdevdn=num_std, 
                    matype=0
                )
                
            # Cek hasilnya valid
            if np.isnan(middle).all():
                logger.warning("TA-Lib BBANDS mengembalikan semua NaN, menggunakan implementasi pandas sebagai fallback")
                return _calculate_bollinger_pandas(prices, window, num_std)
                
            return (pd.Series(upper, index=prices.index),
                    pd.Series(middle, index=prices.index),
                    pd.Series(lower, index=prices.index))
        except Exception as e:
            logger.error(f"Error saat menghitung Bollinger Bands dengan TA-Lib: {str(e)}")
            return _calculate_bollinger_pandas(prices, window, num_std)
    else:
        return _calculate_bollinger_pandas(prices, window, num_std)


def _calculate_bollinger_pandas(prices: pd.Series, window: int = 20, num_std: float = 2.0) -> Tuple[pd.Series, pd.Series, pd.Series]:
    """Implementasi Bollinger Bands menggunakan pandas"""
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
    # Pastikan ada cukup data untuk perhitungan
    if len(prices) < k_period + d_period:
        logger.warning(f"Tidak cukup data untuk menghitung Stochastic. Minimal {k_period + d_period} titik data diperlukan.")
        empty_series = pd.Series(np.nan, index=prices.index)
        return empty_series, empty_series
        
    if TALIB_AVAILABLE:
        try:
            # Pastikan input adalah array numpy yang valid
            if np.isnan(high_prices.values).any() or np.isnan(low_prices.values).any() or np.isnan(prices.values).any():
                high_cleaned = high_prices.fillna(method='ffill').fillna(method='bfill')
                low_cleaned = low_prices.fillna(method='ffill').fillna(method='bfill')
                close_cleaned = prices.fillna(method='ffill').fillna(method='bfill')
                k, d = talib.STOCH(
                    high_cleaned.values, 
                    low_cleaned.values, 
                    close_cleaned.values, 
                    fastk_period=k_period, 
                    slowk_period=d_period, 
                    slowk_matype=0, 
                    slowd_period=d_period, 
                    slowd_matype=0
                )
            else:
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
                
            # Cek hasilnya valid
            if np.isnan(k).all() or np.isnan(d).all():
                logger.warning("TA-Lib STOCH mengembalikan semua NaN, menggunakan implementasi pandas sebagai fallback")
                return _calculate_stochastic_pandas(prices, high_prices, low_prices, k_period, d_period)
                
            return pd.Series(k, index=prices.index), pd.Series(d, index=prices.index)
        except Exception as e:
            logger.error(f"Error saat menghitung Stochastic dengan TA-Lib: {str(e)}")
            return _calculate_stochastic_pandas(prices, high_prices, low_prices, k_period, d_period)
    else:
        return _calculate_stochastic_pandas(prices, high_prices, low_prices, k_period, d_period)


def _calculate_stochastic_pandas(prices: pd.Series, 
                              high_prices: pd.Series, 
                              low_prices: pd.Series,
                              k_period: int = 14, 
                              d_period: int = 3) -> Tuple[pd.Series, pd.Series]:
    """Implementasi Stochastic Oscillator menggunakan pandas"""
    # Calculate Stochastic Oscillator using pandas
    lowest_low = low_prices.rolling(window=k_period).min()
    highest_high = high_prices.rolling(window=k_period).max()
    
    # Hindari pembagian dengan nol
    denominator = highest_high - lowest_low
    denominator = denominator.replace(0, np.finfo(float).eps)
    
    k = 100 * (prices - lowest_low) / denominator
    d = k.rolling(window=d_period).mean()
    
    return k, d


def generate_trading_signals(prices_df: pd.DataFrame, 
                           indicator_periods: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    """
    Generate trading signals based on technical indicators
    
    Args:
        prices_df: DataFrame with price data (must have 'close' column, 
                  optionally 'high', 'low', 'volume')
        indicator_periods: Dictionary of indicator periods:
            - rsi_period: RSI period (default 14)
            - macd_fast: MACD fast period (default 12)
            - macd_slow: MACD slow period (default 26)
            - macd_signal: MACD signal period (default 9)
            - bb_period: Bollinger Bands period (default 20)
            - stoch_k: Stochastic K period (default 14)
            - stoch_d: Stochastic D period (default 3)
            - ma_short: Short moving average period (default 20)
            - ma_medium: Medium moving average period (default 50)
            - ma_long: Long moving average period (default 200)
        
    Returns:
        dict: Trading signals and analysis
    """
    logger.info("Generating trading signals")
    
    # Default period jika tidak ada yang disediakan
    if indicator_periods is None:
        indicator_periods = {}
    
    # Ekstrak periode indikator atau gunakan default
    rsi_period = indicator_periods.get('rsi_period', 14)
    macd_fast = indicator_periods.get('macd_fast', 12)
    macd_slow = indicator_periods.get('macd_slow', 26)
    macd_signal = indicator_periods.get('macd_signal', 9)
    bb_period = indicator_periods.get('bb_period', 20)
    stoch_k = indicator_periods.get('stoch_k', 14)
    stoch_d = indicator_periods.get('stoch_d', 3)
    ma_short = indicator_periods.get('ma_short', 20)
    ma_medium = indicator_periods.get('ma_medium', 50)
    ma_long = indicator_periods.get('ma_long', 200)
    
    # Log periode indikator yang digunakan
    logger.info(f"Indikator Periods - RSI: {rsi_period}, MACD: {macd_fast}/{macd_slow}/{macd_signal}, "
               f"BB: {bb_period}, Stoch: {stoch_k}/{stoch_d}, MA: {ma_short}/{ma_medium}/{ma_long}")
    
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
    
    # Pastikan minimal data cukup untuk perhitungan
    # Hitung minimal data yang diperlukan berdasarkan indikator terlama
    min_required_points = max(
        3 * rsi_period,
        macd_slow + macd_signal + 10,
        bb_period + 10,
        stoch_k + stoch_d + 5,
        ma_long + 10
    )
    
    # Perubahan di sini: Sesuaikan kebutuhan data berdasarkan trading style
    # Jika periode MA jangka panjang kurang dari 100, tidak perlu data sangat panjang
    is_short_term = ma_long <= 100
    if is_short_term:
        # Untuk analisis jangka pendek, kurangi kebutuhan data
        min_required_points = min(min_required_points, 60)
        logger.info(f"Analisis jangka pendek terdeteksi, kebutuhan data minimum disesuaikan menjadi {min_required_points}")
    elif ma_long > 100:
        # Sanity check: minimum 30 days untuk semua analisis
        min_required_points = max(30, min_required_points)
    
    if len(prices_df) < min_required_points:
        logger.warning(f"Tidak cukup data untuk analisis teknikal yang akurat. Minimal {min_required_points} titik data diperlukan, hanya {len(prices_df)} tersedia.")
        
        # Jika terlalu sedikit data, berikan error yang lebih spesifik
        if len(prices_df) < 20:
            logger.info(f"Data terlalu sedikit untuk analisis teknikal dasar")
            return {
                "error": f"Data tidak cukup untuk analisis. Dibutuhkan minimal 20 titik data, hanya tersedia {len(prices_df)}.",
                "action": "hold",
                "confidence": 0.0,
                "min_days_needed": min_required_points
            }
        
        # Jika data cukup untuk analisis dasar tapi kurang dari ideal, lanjutkan dengan warning
        logger.info(f"Coba tambahkan parameter 'days' dengan nilai lebih besar (misalnya, {min_required_points + 10})")
        # Di sini kita lanjutkan dengan perhitungan meskipun data kurang ideal
    
    # Use appropriate column names or defaults
    close_col = 'close'
    high_col = 'high' if 'high' in prices_df.columns else close_col
    low_col = 'low' if 'low' in prices_df.columns else close_col
    
    # Extract price series
    close_prices = prices_df[close_col]
    high_prices = prices_df[high_col]
    low_prices = prices_df[low_col]
    
    # Check if TA-Lib is available and log it
    if TALIB_AVAILABLE:
        logger.info("Menggunakan TA-Lib untuk perhitungan indikator teknikal")
    else:
        logger.info("TA-Lib tidak tersedia, menggunakan implementasi pandas")
    
    # Calculate indicators
    # 1. RSI
    rsi = calculate_rsi(close_prices, window=rsi_period)
    
    # 2. MACD
    macd, signal, histogram = calculate_macd(close_prices, 
                                           fast_period=macd_fast, 
                                           slow_period=macd_slow, 
                                           signal_period=macd_signal)
    
    # 3. Bollinger Bands
    upper_band, middle_band, lower_band = calculate_bollinger_bands(close_prices, window=bb_period)
    
    # 4. Stochastic Oscillator
    k, d = calculate_stochastic(close_prices, high_prices, low_prices, 
                              k_period=stoch_k, d_period=stoch_d)
    
    # 5. ADX
    adx, plus_di, minus_di = calculate_adx(high_prices, low_prices, close_prices)
    
    # 6. Moving Averages
    sma_short = close_prices.rolling(window=ma_short).mean()
    sma_medium = close_prices.rolling(window=ma_medium).mean()
    sma_long = close_prices.rolling(window=ma_long).mean()
    
    # Log status perhitungan indikator
    for indicator_name, indicator_data in [
        ("RSI", rsi), 
        ("MACD", macd),
        ("MACD Signal", signal),
        ("Bollinger Middle", middle_band),
        ("Stochastic K", k),
        ("ADX", adx)
    ]:
        valid_points = indicator_data.count()
        total_points = len(indicator_data)
        logger.info(f"Indikator {indicator_name}: {valid_points}/{total_points} titik data valid")
    
    # Get latest values
    latest_close = close_prices.iloc[-1]
    latest_rsi = rsi.iloc[-1] if not pd.isna(rsi.iloc[-1]) else 50  # Default ke netral jika tidak valid
    latest_macd = macd.iloc[-1] if not pd.isna(macd.iloc[-1]) else 0
    latest_signal = signal.iloc[-1] if not pd.isna(signal.iloc[-1]) else 0
    latest_histogram = histogram.iloc[-1] if not pd.isna(histogram.iloc[-1]) else 0
    latest_upper = upper_band.iloc[-1] if not pd.isna(upper_band.iloc[-1]) else latest_close * 1.05
    latest_middle = middle_band.iloc[-1] if not pd.isna(middle_band.iloc[-1]) else latest_close
    latest_lower = lower_band.iloc[-1] if not pd.isna(lower_band.iloc[-1]) else latest_close * 0.95
    latest_k = k.iloc[-1] if not pd.isna(k.iloc[-1]) else 50
    latest_d = d.iloc[-1] if not pd.isna(d.iloc[-1]) else 50
    latest_adx = adx.iloc[-1] if not pd.isna(adx.iloc[-1]) else 15
    latest_plus_di = plus_di.iloc[-1] if not pd.isna(plus_di.iloc[-1]) else 20
    latest_minus_di = minus_di.iloc[-1] if not pd.isna(minus_di.iloc[-1]) else 20
    
    # Validasi nilai sma
    latest_sma_short = sma_short.iloc[-1] if not pd.isna(sma_short.iloc[-1]) else latest_close
    latest_sma_medium = sma_medium.iloc[-1] if not pd.isna(sma_medium.iloc[-1]) else latest_close
    latest_sma_long = sma_long.iloc[-1] if not pd.isna(sma_long.iloc[-1]) else latest_close
    
    # Calculate signals
    signals = {}
    
    # RSI signals
    if latest_rsi < 30:
        signals['rsi'] = {
            'signal': 'buy',
            'strength': min(1.0, (30 - latest_rsi) / 10),
            'description': f"RSI oversold di level {latest_rsi:.2f} (periode {rsi_period})"
        }
    elif latest_rsi > 70:
        signals['rsi'] = {
            'signal': 'sell',
            'strength': min(1.0, (latest_rsi - 70) / 10),
            'description': f"RSI overbought di level {latest_rsi:.2f} (periode {rsi_period})"
        }
    else:
        signals['rsi'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"RSI netral di level {latest_rsi:.2f} (periode {rsi_period})"
        }
    
    # MACD signals - perbaiki pengecekan crossover
    # Pastikan kita memiliki setidaknya 2 data poin valid
    valid_macd = macd.dropna()
    valid_signal = signal.dropna()
    
    macd_cross_up = False
    macd_cross_down = False
    
    if len(valid_macd) >= 2 and len(valid_signal) >= 2:
        macd_cross_up = (valid_macd.iloc[-1] > valid_signal.iloc[-1]) and (valid_macd.iloc[-2] <= valid_signal.iloc[-2])
        macd_cross_down = (valid_macd.iloc[-1] < valid_signal.iloc[-1]) and (valid_macd.iloc[-2] >= valid_signal.iloc[-2])
    
    if macd_cross_up:
        signals['macd'] = {
            'signal': 'buy',
            'strength': 0.8,
            'description': f"MACD memotong ke atas signal line (bullish) - ({macd_fast}/{macd_slow}/{macd_signal})"
        }
    elif macd_cross_down:
        signals['macd'] = {
            'signal': 'sell',
            'strength': 0.8,
            'description': f"MACD memotong ke bawah signal line (bearish) - ({macd_fast}/{macd_slow}/{macd_signal})"
        }
    elif latest_macd > latest_signal:
        signals['macd'] = {
            'signal': 'buy',
            'strength': 0.6,
            'description': f"MACD di atas signal line (bullish) - ({macd_fast}/{macd_slow}/{macd_signal})"
        }
    elif latest_macd < latest_signal:
        signals['macd'] = {
            'signal': 'sell',
            'strength': 0.6,
            'description': f"MACD di bawah signal line (bearish) - ({macd_fast}/{macd_slow}/{macd_signal})"
        }
    else:
        signals['macd'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"MACD netral - ({macd_fast}/{macd_slow}/{macd_signal})"
        }
    
    # Bollinger Bands signals
    # Cek apakah nilai Bollinger Bands valid
    if not pd.isna(latest_upper) and not pd.isna(latest_lower) and latest_upper > latest_lower:
        bb_percent = (latest_close - latest_lower) / (latest_upper - latest_lower)
        
        if latest_close > latest_upper:
            signals['bollinger'] = {
                'signal': 'sell',
                'strength': 0.7,
                'description': f"Harga di atas upper Bollinger Band (overbought) - (periode {bb_period})"
            }
        elif latest_close < latest_lower:
            signals['bollinger'] = {
                'signal': 'buy',
                'strength': 0.7,
                'description': f"Harga di bawah lower Bollinger Band (oversold) - (periode {bb_period})"
            }
        elif bb_percent > 0.8:
            signals['bollinger'] = {
                'signal': 'sell',
                'strength': 0.6,
                'description': f"Harga mendekati upper Bollinger Band (potensi pembalikan) - (periode {bb_period})"
            }
        elif bb_percent < 0.2:
            signals['bollinger'] = {
                'signal': 'buy',
                'strength': 0.6,
                'description': f"Harga mendekati lower Bollinger Band (potensi pembalikan) - (periode {bb_period})"
            }
        else:
            signals['bollinger'] = {
                'signal': 'hold',
                'strength': 0.5,
                'description': f"Harga di dalam Bollinger Bands (netral) - (periode {bb_period})"
            }
    else:
        signals['bollinger'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"Harga di dalam Bollinger Bands (netral) - (periode {bb_period})"
        }
    
    # Stochastic signals - perbaiki pengecekan crossover
    valid_k = k.dropna()
    valid_d = d.dropna()
    
    stoch_cross_up = False
    stoch_cross_down = False
    
    if len(valid_k) >= 2 and len(valid_d) >= 2:
        stoch_cross_up = (valid_k.iloc[-1] > valid_d.iloc[-1]) and (valid_k.iloc[-2] <= valid_d.iloc[-2])
        stoch_cross_down = (valid_k.iloc[-1] < valid_d.iloc[-1]) and (valid_k.iloc[-2] >= valid_d.iloc[-2])
    
    if latest_k < 20 and stoch_cross_up:
        signals['stochastic'] = {
            'signal': 'buy',
            'strength': 0.8,
            'description': f"Stochastic %K memotong ke atas %D di zona oversold (sinyal beli kuat) - ({stoch_k}/{stoch_d})"
        }
    elif latest_k > 80 and stoch_cross_down:
        signals['stochastic'] = {
            'signal': 'sell',
            'strength': 0.8,
            'description': f"Stochastic %K memotong ke bawah %D di zona overbought (sinyal jual kuat) - ({stoch_k}/{stoch_d})"
        }
    elif latest_k < 20:
        signals['stochastic'] = {
            'signal': 'buy',
            'strength': 0.7,
            'description': f"Stochastic oscillator oversold di level {latest_k:.2f} - ({stoch_k}/{stoch_d})"
        }
    elif latest_k > 80:
        signals['stochastic'] = {
            'signal': 'sell',
            'strength': 0.7,
            'description': f"Stochastic oscillator overbought di level {latest_k:.2f} - ({stoch_k}/{stoch_d})"
        }
    else:
        signals['stochastic'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"Stochastic oscillator netral - ({stoch_k}/{stoch_d})"
        }
    
    # ADX signals
    if latest_adx > 25:
        if latest_plus_di > latest_minus_di:
            signals['adx'] = {
                'signal': 'buy',
                'strength': min(1.0, latest_adx / 50),
                'description': f"Tren kuat dengan +DI > -DI (ADX: {latest_adx:.2f})"
            }
        else:
            signals['adx'] = {
                'signal': 'sell',
                'strength': min(1.0, latest_adx / 50),
                'description': f"Tren kuat dengan -DI > +DI (ADX: {latest_adx:.2f})"
            }
    else:
        signals['adx'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"Tren lemah (ADX: {latest_adx:.2f})"
        }
    
    # Moving Average signals
    # Perbaiki pengecekan golden cross / death cross
    valid_sma_medium = sma_medium.dropna()
    valid_sma_long = sma_long.dropna()
    
    golden_cross = False
    death_cross = False
    
    if len(valid_sma_medium) >= 2 and len(valid_sma_long) >= 2:
        golden_cross = (valid_sma_medium.iloc[-1] > valid_sma_long.iloc[-1]) and (valid_sma_medium.iloc[-2] <= valid_sma_long.iloc[-2])
        death_cross = (valid_sma_medium.iloc[-1] < valid_sma_long.iloc[-1]) and (valid_sma_medium.iloc[-2] >= valid_sma_long.iloc[-2])
    
    if golden_cross:
        signals['moving_avg'] = {
            'signal': 'buy',
            'strength': 0.9,
            'description': f"Golden Cross: MA {ma_medium} hari memotong di atas MA {ma_long} hari (sinyal beli kuat)"
        }
    elif death_cross:
        signals['moving_avg'] = {
            'signal': 'sell',
            'strength': 0.9,
            'description': f"Death Cross: MA {ma_medium} hari memotong di bawah MA {ma_long} hari (sinyal jual kuat)"
        }
    elif latest_close > latest_sma_short and latest_sma_short > latest_sma_medium and latest_sma_medium > latest_sma_long:
        signals['moving_avg'] = {
            'signal': 'buy',
            'strength': 0.8,
            'description': f"Harga di atas semua moving average utama (tren bullish) - MA {ma_short}/{ma_medium}/{ma_long}"
        }
    elif latest_close < latest_sma_short and latest_sma_short < latest_sma_medium and latest_sma_medium < latest_sma_long:
        signals['moving_avg'] = {
            'signal': 'sell',
            'strength': 0.8,
            'description': f"Harga di bawah semua moving average utama (tren bearish) - MA {ma_short}/{ma_medium}/{ma_long}"
        }
    elif latest_close > latest_sma_long:
        signals['moving_avg'] = {
            'signal': 'buy',
            'strength': 0.6,
            'description': f"Harga di atas MA {ma_long} hari (bullish jangka panjang)"
        }
    elif latest_close < latest_sma_long:
        signals['moving_avg'] = {
            'signal': 'sell',
            'strength': 0.6,
            'description': f"Harga di bawah MA {ma_long} hari (bearish jangka panjang)"
        }
    else:
        signals['moving_avg'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"Moving average netral - MA {ma_short}/{ma_medium}/{ma_long}"
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

    if 'high' in prices_df.columns and 'low' in prices_df.columns:
        atr = calculate_atr(high_prices, low_prices, close_prices)
        latest_atr = atr.iloc[-1] if not pd.isna(atr.iloc[-1]) else (latest_close * 0.02)  # Default to 2% of price
        
        if action == "buy":
            # Target price for buying: current price + 2*ATR
            # Use abs() to ensure positive ATR value
            target_price = latest_close + (2 * abs(latest_atr))
        elif action == "sell":
            # Target price for selling: current price - 2*ATR
            # Use abs() to ensure positive ATR value
            target_price = latest_close - (2 * abs(latest_atr))
    
    # Check if confidence meets threshold
    strong_signal = confidence >= CONFIDENCE_THRESHOLD
    
    # Calculate Bollinger Percent B untuk API response
    if pd.isna(latest_upper) or pd.isna(latest_lower) or latest_upper == latest_lower:
        bb_percent_b = 0.5  # Default ke nilai netral
    else:
        bb_percent_b = (latest_close - latest_lower) / (latest_upper - latest_lower)
    
    # Create final recommendation
    recommendation = {
        "action": action,
        "confidence": float(confidence),
        "strong_signal": strong_signal,
        "evidence": evidence,
        "indicators": {
            "rsi": float(np.nan_to_num(latest_rsi)),
            "macd": float(np.nan_to_num(latest_macd)),
            "macd_signal": float(np.nan_to_num(latest_signal)),
            "macd_histogram": float(np.nan_to_num(latest_histogram)),
            "bollinger_percent": float(np.nan_to_num(bb_percent_b)),
            "stochastic_k": float(np.nan_to_num(latest_k)),
            "stochastic_d": float(np.nan_to_num(latest_d)),
            "adx": float(np.nan_to_num(latest_adx))
        },
        "indicator_periods": {
            "rsi_period": rsi_period,
            "macd_fast": macd_fast,
            "macd_slow": macd_slow,
            "macd_signal": macd_signal,
            "bb_period": bb_period,
            "stoch_k": stoch_k,
            "stoch_d": stoch_d,
            "ma_short": ma_short,
            "ma_medium": ma_medium,
            "ma_long": ma_long
        }
    }
    
    if target_price is not None and not pd.isna(target_price):
        recommendation["target_price"] = float(target_price)
    
    return recommendation


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
    # Pastikan ada cukup data untuk perhitungan
    if len(close_prices) < window + 1:  # +1 untuk previous close
        logger.warning(f"Tidak cukup data untuk menghitung ATR. Minimal {window + 1} titik data diperlukan.")
        return pd.Series(np.nan, index=close_prices.index)
        
    if TALIB_AVAILABLE:
        try:
            # Pastikan input adalah array numpy yang valid
            if np.isnan(high_prices.values).any() or np.isnan(low_prices.values).any() or np.isnan(close_prices.values).any():
                high_cleaned = high_prices.fillna(method='ffill').fillna(method='bfill')
                low_cleaned = low_prices.fillna(method='ffill').fillna(method='bfill')
                close_cleaned = close_prices.fillna(method='ffill').fillna(method='bfill')
                atr = talib.ATR(
                    high_cleaned.values, 
                    low_cleaned.values, 
                    close_cleaned.values, 
                    timeperiod=window
                )
            else:
                atr = talib.ATR(
                    high_prices.values, 
                    low_prices.values, 
                    close_prices.values, 
                    timeperiod=window
                )
                
            # Cek hasilnya valid
            if np.isnan(atr).all():
                logger.warning("TA-Lib ATR mengembalikan semua NaN, menggunakan implementasi pandas sebagai fallback")
                return _calculate_atr_pandas(high_prices, low_prices, close_prices, window)
                
            return pd.Series(atr, index=close_prices.index)
        except Exception as e:
            logger.error(f"Error saat menghitung ATR dengan TA-Lib: {str(e)}")
            return _calculate_atr_pandas(high_prices, low_prices, close_prices, window)
    else:
        return _calculate_atr_pandas(high_prices, low_prices, close_prices, window)


def _calculate_atr_pandas(high_prices: pd.Series, 
                      low_prices: pd.Series, 
                      close_prices: pd.Series, 
                      window: int = 14) -> pd.Series:
    """Implementasi ATR menggunakan pandas"""
    # Calculate ATR using pandas
    prev_close = close_prices.shift(1)
    tr1 = high_prices - low_prices  # High - Low
    tr2 = (high_prices - prev_close).abs()  # |High - Previous Close|
    tr3 = (low_prices - prev_close).abs()  # |Low - Previous Close|
    
    # Handle NaN values in each component
    tr1 = tr1.fillna(0)
    tr2 = tr2.fillna(0)
    tr3 = tr3.fillna(0)
    
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
    # Pastikan ada cukup data untuk perhitungan
    min_periods_needed = window * 3  # ADX membutuhkan banyak data historis
    if len(close_prices) < min_periods_needed:
        logger.warning(f"Tidak cukup data untuk menghitung ADX. Minimal {min_periods_needed} titik data diperlukan.")
        empty_series = pd.Series(np.nan, index=close_prices.index)
        return empty_series, empty_series, empty_series
        
    if TALIB_AVAILABLE:
        try:
            # Pastikan input adalah array numpy yang valid
            if np.isnan(high_prices.values).any() or np.isnan(low_prices.values).any() or np.isnan(close_prices.values).any():
                high_cleaned = high_prices.fillna(method='ffill').fillna(method='bfill')
                low_cleaned = low_prices.fillna(method='ffill').fillna(method='bfill')
                close_cleaned = close_prices.fillna(method='ffill').fillna(method='bfill')
                adx = talib.ADX(
                    high_cleaned.values, 
                    low_cleaned.values, 
                    close_cleaned.values, 
                    timeperiod=window
                )
                plus_di = talib.PLUS_DI(
                    high_cleaned.values, 
                    low_cleaned.values, 
                    close_cleaned.values, 
                    timeperiod=window
                )
                minus_di = talib.MINUS_DI(
                    high_cleaned.values, 
                    low_cleaned.values, 
                    close_cleaned.values, 
                    timeperiod=window
                )
            else:
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
                
            # Cek hasilnya valid
            if np.isnan(adx).all() or np.isnan(plus_di).all() or np.isnan(minus_di).all():
                logger.warning("TA-Lib ADX mengembalikan semua NaN, menggunakan implementasi pandas sebagai fallback")
                return _calculate_adx_pandas(high_prices, low_prices, close_prices, window)
                
            return (pd.Series(adx, index=close_prices.index),
                    pd.Series(plus_di, index=close_prices.index),
                    pd.Series(minus_di, index=close_prices.index))
        except Exception as e:
            logger.error(f"Error saat menghitung ADX dengan TA-Lib: {str(e)}")
            return _calculate_adx_pandas(high_prices, low_prices, close_prices, window)
    else:
        return _calculate_adx_pandas(high_prices, low_prices, close_prices, window)


def _calculate_adx_pandas(high_prices: pd.Series, 
                      low_prices: pd.Series, 
                      close_prices: pd.Series, 
                      window: int = 14) -> Tuple[pd.Series, pd.Series, pd.Series]:
    """Implementasi ADX menggunakan pandas"""
    try:
        # 1. True Range
        prev_close = close_prices.shift(1)
        tr1 = high_prices - low_prices  # High - Low
        tr2 = (high_prices - prev_close).abs()  # |High - Previous Close|
        tr3 = (low_prices - prev_close).abs()  # |Low - Previous Close|
        
        # Handle NaN values
        tr1 = tr1.fillna(0)
        tr2 = tr2.fillna(0)
        tr3 = tr3.fillna(0)
        
        true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
        atr = true_range.rolling(window=window).mean()
        
        # 2. Directional Movement
        high_diff = high_prices - high_prices.shift(1)
        low_diff = low_prices.shift(1) - low_prices
        
        # Handle NaN values
        high_diff = high_diff.fillna(0)
        low_diff = low_diff.fillna(0)
        
        plus_dm = high_diff.where((high_diff > low_diff) & (high_diff > 0), 0)
        minus_dm = low_diff.where((low_diff > high_diff) & (low_diff > 0), 0)
        
        # 3. Directional Indicators
        # Avoid division by zero
        atr_safe = atr.replace(0, np.finfo(float).eps)
        
        plus_di = 100 * (plus_dm.rolling(window=window).mean() / atr_safe)
        minus_di = 100 * (minus_dm.rolling(window=window).mean() / atr_safe)
        
        # 4. Directional Index
        plus_minus_sum = plus_di + minus_di
        plus_minus_sum_safe = plus_minus_sum.replace(0, np.finfo(float).eps)
        
        dx = 100 * ((plus_di - minus_di).abs() / plus_minus_sum_safe)
        
        # 5. Average Directional Index
        adx = dx.rolling(window=window).mean()
        
        return adx, plus_di, minus_di
    except Exception as e:
        logger.error(f"Error dalam perhitungan ADX pandas: {str(e)}")
        # Return empty series if calculation fails
        empty_series = pd.Series(np.nan, index=close_prices.index)
        return empty_series, empty_series, empty_series


def personalize_signals(signals: Dict[str, Any], 
                      risk_tolerance: str = 'medium') -> Dict[str, Any]:
    """
    Personalize trading signals based on user risk tolerance
    """
    # Create a copy to avoid modifying the original
    personalized = signals.copy()
    
    # Jika ada error dalam signals, langsung return
    if 'error' in signals:
        personalized['personalized_message'] = "Tidak dapat membuat sinyal personal: " + signals.get('error', 'Unknown error')
        personalized['risk_profile'] = risk_tolerance
        return personalized
    
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
            personalized['personalized_message'] = "Sinyal tidak cukup kuat untuk profil risiko konservatif Anda"
        else:
            personalized['personalized_message'] = "Sinyal kuat sesuai dengan profil risiko konservatif Anda"
            
    elif risk_tolerance == 'high':
        # Aggressive: Amplify confidence for buy/sell
        if action != 'hold':
            boosted_confidence = min(1.0, confidence * 1.2)
            personalized['confidence'] = boosted_confidence
            personalized['personalized_message'] = "Sinyal diperkuat untuk profil risiko agresif Anda"
        else:
            # Convert weak buy/sell signals to action for aggressive profiles
            buy_signals = sum(1 for s in signals.get('evidence', []) if 'buy' in s.lower())
            sell_signals = sum(1 for s in signals.get('evidence', []) if 'sell' in s.lower())
            
            if buy_signals > sell_signals and buy_signals > 1:
                personalized['action'] = 'buy'
                personalized['confidence'] = 0.5
                personalized['personalized_message'] = "Dikonversi ke sinyal beli untuk profil risiko agresif Anda"
            elif sell_signals > buy_signals and sell_signals > 1:
                personalized['action'] = 'sell'
                personalized['confidence'] = 0.5
                personalized['personalized_message'] = "Dikonversi ke sinyal jual untuk profil risiko agresif Anda"
            else:
                personalized['personalized_message'] = "Tetap hold meskipun profil risiko Anda agresif karena sinyal tidak jelas"
                
    else:  # medium
        personalized['personalized_message'] = "Sinyal sesuai dengan profil risiko seimbang Anda"
    
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
    
    # Pastikan ada cukup data untuk perhitungan
    if len(prices_df) < window * 2:
        logger.warning(f"Tidak cukup data untuk deteksi market events. Minimal {window * 2} titik data diperlukan.")
        return {
            "error": f"Insufficient data for market event detection. Need at least {window * 2} points.",
            "latest_event": "unknown",
            "event_counts": {"pump": 0, "dump": 0, "high_volatility": 0}
        }
    
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
    
    # Detect events (pastikan data cukup untuk perhitungan)
    valid_data = ~(close_prices.isna() | ma.isna() | std.isna() | returns.isna())
    
    events = {
        "pump": (close_prices > upper_bound) & valid_data,
        "dump": (close_prices < lower_bound) & valid_data,
        "high_volatility": (returns.abs() > (returns.std() * threshold)) & valid_data
    }
    
    # Count events
    event_counts = {
        "pump": events["pump"].sum(),
        "dump": events["dump"].sum(),
        "high_volatility": events["high_volatility"].sum()
    }
    
    # Get latest event (if any)
    latest_event = "normal"
    
    if len(events["pump"]) > 0 and events["pump"].iloc[-1]:
        latest_event = "pump"
    elif len(events["dump"]) > 0 and events["dump"].iloc[-1]:
        latest_event = "dump"
    elif len(events["high_volatility"]) > 0 and events["high_volatility"].iloc[-1]:
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
    
    # Test dengan berbagai periode indikator
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
    
    # Test jangka pendek
    print("\n=== Test Indikator Jangka Pendek ===")
    short_term = {
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
    
    signals_short = generate_trading_signals(df, short_term)
    print(f"Jangka Pendek - Action: {signals_short['action'].upper()}, Confidence: {signals_short['confidence']:.2f}")
    
    # Test jangka panjang
    print("\n=== Test Indikator Jangka Panjang ===")
    long_term = {
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
    
    signals_long = generate_trading_signals(df, long_term)
    print(f"Jangka Panjang - Action: {signals_long['action'].upper()}, Confidence: {signals_long['confidence']:.2f}")
    
    # Test standard
    signals = generate_trading_signals(df, indicator_periods)
    
    # Print results
    print("\n=== Technical Analysis Signals (Standard) ===")
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