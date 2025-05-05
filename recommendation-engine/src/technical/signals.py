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


def detect_market_regime(prices_df: pd.DataFrame, window: int = 30) -> str:
    # Pastikan ada cukup data
    if len(prices_df) < window * 2:
        logger.warning(f"Tidak cukup data untuk deteksi regime pasar, minimal {window*2} titik data diperlukan")
        
        # Fallback - deteksi sederhana jika data minimal tersedia
        if len(prices_df) >= 5:
            # Gunakan data yang tersedia untuk deteksi sederhana
            close_prices = prices_df['close']
            
            # Hitung returns sederhana (5-day)
            returns = close_prices.pct_change(5).dropna()
            
            if len(returns) > 0:
                # Hitung volatilitas sederhana
                volatility = returns.std() * np.sqrt(252)  # Annualized
                
                # Deteksi arah tren sederhana - bandingkan harga awal dan akhir
                trend = close_prices.iloc[-1] / close_prices.iloc[0] - 1
                
                # Deteksi regime berdasarkan data terbatas
                if volatility > 0.5:  # High volatility
                    if trend > 0.05:  # 5% up
                        return "volatile_bullish"
                    elif trend < -0.05:  # 5% down
                        return "volatile_bearish"
                    else:
                        return "ranging_volatile"
                else:
                    if trend > 0.03:  # 3% up
                        return "trending_bullish"
                    elif trend < -0.03:  # 3% down
                        return "trending_bearish"
                    else:
                        return "ranging_low_volatility"
                        
        return "unknown"  # Default jika data sangat terbatas
    
    # Extract price data
    close_prices = prices_df['close']
    
    # Hitung returns
    returns = close_prices.pct_change().dropna()
    
    # Hitung volatilitas
    volatility = returns.rolling(window=window).std() * np.sqrt(252)  # Annualized
    current_volatility = volatility.iloc[-1] if not pd.isna(volatility.iloc[-1]) else 0.3
    
    # Hitung ADX - kekuatan tren
    if 'high' in prices_df.columns and 'low' in prices_df.columns:
        adx, plus_di, minus_di = calculate_adx(
            prices_df['high'], 
            prices_df['low'], 
            close_prices,
            window=14
        )
        current_adx = adx.iloc[-1] if not pd.isna(adx.iloc[-1]) else 15
        current_plus_di = plus_di.iloc[-1] if not pd.isna(plus_di.iloc[-1]) else 15
        current_minus_di = minus_di.iloc[-1] if not pd.isna(minus_di.iloc[-1]) else 15
    else:
        # Fallback jika kolom high/low tidak tersedia
        current_adx = 15
        current_plus_di = 15
        current_minus_di = 15
    
    # Hitung slope dari moving average untuk arah tren
    ma_short = close_prices.rolling(window=20).mean()
    ma_slope = (ma_short.iloc[-1] - ma_short.iloc[-window]) / ma_short.iloc[-window] if len(ma_short) > window else 0
    
    # Logika deteksi regime
    if current_adx > 25:  # Tren kuat
        if ma_slope > 0 and current_plus_di > current_minus_di:
            if current_volatility > 0.5:  # Volatile
                return "trending_bullish_volatile"
            else:
                return "trending_bullish"
        elif ma_slope < 0 and current_minus_di > current_plus_di:
            if current_volatility > 0.5:  # Volatile
                return "trending_bearish_volatile"
            else:
                return "trending_bearish"
        else:
            return "trending_neutral"
    else:  # Tren lemah / ranging
        if current_volatility > 0.5:
            return "ranging_volatile"
        else:
            return "ranging_low_volatility"

def get_optimal_parameters(prices_df: pd.DataFrame, market_regime: str = None, 
                          trading_style: str = 'standard') -> Dict[str, Any]:
    # Deteksi regime pasar jika tidak disediakan
    if market_regime is None:
        market_regime = detect_market_regime(prices_df)
    
    logger.info(f"Menentukan parameter optimal untuk regime: {market_regime}, style: {trading_style}")
    
    # Parameter dasar berdasarkan trading style
    if trading_style == 'short_term':
        base_params = {
            'rsi_period': 7,
            'macd_fast': 8,
            'macd_slow': 17,
            'macd_signal': 9,
            'bb_period': 10,
            'stoch_k': 7,
            'stoch_d': 3,
            'ma_short': 10,
            'ma_medium': 30,
            'ma_long': 60,
            'atr_period': 7,
            'adx_period': 7
        }
    elif trading_style == 'long_term':
        base_params = {
            'rsi_period': 21,
            'macd_fast': 19,
            'macd_slow': 39,
            'macd_signal': 9,
            'bb_period': 30,
            'stoch_k': 21,
            'stoch_d': 7,
            'ma_short': 50,
            'ma_medium': 100,
            'ma_long': 200,
            'atr_period': 21,
            'adx_period': 21
        }
    else:  # standard
        base_params = {
            'rsi_period': 14,
            'macd_fast': 12,
            'macd_slow': 26,
            'macd_signal': 9,
            'bb_period': 20,
            'stoch_k': 14,
            'stoch_d': 3,
            'ma_short': 20,
            'ma_medium': 50,
            'ma_long': 200,
            'atr_period': 14,
            'adx_period': 14
        }
    
    # Adaptasi parameter berdasarkan market regime
    if 'trending_bullish' in market_regime:
        # Untuk tren bullish, fokus pada indikator momentum dan tren
        params = base_params.copy()
        # Kurangi periode RSI untuk mendeteksi momentum lebih cepat
        params['rsi_period'] = max(5, int(base_params['rsi_period'] * 0.8))
        # Perkecil periode MACD untuk respon lebih cepat
        params['macd_fast'] = max(6, int(base_params['macd_fast'] * 0.9))
        params['macd_slow'] = max(12, int(base_params['macd_slow'] * 0.9))
        # Perkecil periode Bollinger untuk respon lebih cepat
        params['bb_period'] = max(8, int(base_params['bb_period'] * 0.9))
        
    elif 'trending_bearish' in market_regime:
        # Untuk tren bearish, fokus pada overbought conditions dan sinyal pembalikan
        params = base_params.copy()
        # Periode RSI normal untuk mendeteksi oversold conditions
        # Perbesar periode Bollinger sedikit untuk konfirmasi lebih kuat
        params['bb_period'] = min(40, int(base_params['bb_period'] * 1.1))
        
    elif 'ranging_volatile' in market_regime:
        # Untuk pasar sideways dengan volatilitas tinggi, gunakan oscillators
        params = base_params.copy()
        # Perkecil periode RSI dan stochastic untuk mendeteksi oversold/overbought
        params['rsi_period'] = max(5, int(base_params['rsi_period'] * 0.7))
        params['stoch_k'] = max(5, int(base_params['stoch_k'] * 0.7))
        # Perbesar Bollinger untuk menangkap range lebih baik
        params['bb_period'] = min(40, int(base_params['bb_period'] * 1.2))
        
    elif 'ranging_low_volatility' in market_regime:
        # Untuk pasar sideways dengan volatilitas rendah, gunakan oscillators dengan periode lebih panjang
        params = base_params.copy()
        # Perbesar periode untuk mengurangi noise
        params['rsi_period'] = min(30, int(base_params['rsi_period'] * 1.2))
        params['macd_fast'] = min(20, int(base_params['macd_fast'] * 1.1))
        params['macd_slow'] = min(40, int(base_params['macd_slow'] * 1.1))
        params['bb_period'] = min(40, int(base_params['bb_period'] * 1.2))
        
    else:  # Default/unknown
        params = base_params.copy()
    
    # Jika volatilitas tinggi, sesuaikan beberapa parameter
    if 'volatile' in market_regime:
        # Perbesar periode Bollinger dan RSI untuk filter noise
        params['bb_period'] = min(40, int(params['bb_period'] * 1.1))
        params['rsi_period'] = max(5, min(30, int(params['rsi_period'] * 1.1)))
    
    # Batasi parameter agar tidak terlalu ekstrem
    for key, value in params.items():
        if key in ['rsi_period', 'stoch_k', 'macd_fast']:
            params[key] = max(5, value)  # Minimal 5
        elif key in ['macd_slow', 'macd_signal', 'stoch_d', 'bb_period']:
            params[key] = max(7, value)  # Minimal 7
    
    return params


def weighted_signal_ensemble(signals: Dict[str, Dict[str, Any]], 
                           market_regime: str) -> Dict[str, Any]:
    # Default weights
    weights = {
        'rsi': 0.15,
        'macd': 0.20,
        'bollinger': 0.15,
        'stochastic': 0.10,
        'adx': 0.10,
        'moving_avg': 0.30
    }
    
    # Sesuaikan bobot berdasarkan market regime
    if 'trending_bullish' in market_regime:
        # Prioritaskan tren indikator untuk market bullish
        weights = {
            'rsi': 0.15,         # RSI penting untuk momentum
            'macd': 0.25,        # MACD penting untuk tren
            'bollinger': 0.10,   # Bollinger kurang penting dalam tren jelas
            'stochastic': 0.10,  # Stochastic untuk konfirmasi momentum
            'adx': 0.15,         # ADX penting untuk kekuatan tren
            'moving_avg': 0.25   # Moving average sangat penting dalam tren
        }
    elif 'trending_bearish' in market_regime:
        # Prioritaskan indikator reversal untuk market bearish
        weights = {
            'rsi': 0.20,         # RSI penting untuk deteksi oversold
            'macd': 0.20,        # MACD untuk momentum
            'bollinger': 0.15,   # Bollinger untuk boundaries
            'stochastic': 0.15,  # Stochastic untuk konfirmasi
            'adx': 0.10,         # ADX untuk kekuatan tren
            'moving_avg': 0.20   # Moving average untuk arah tren
        }
    elif 'ranging_volatile' in market_regime:
        # Prioritaskan oscillators untuk market ranging volatile
        weights = {
            'rsi': 0.25,         # RSI sangat penting di market ranging
            'macd': 0.10,        # MACD kurang penting
            'bollinger': 0.30,   # Bollinger penting untuk range boundaries
            'stochastic': 0.20,  # Stochastic penting untuk overbought/oversold
            'adx': 0.05,         # ADX kurang relevan
            'moving_avg': 0.10   # MA kurang penting dalam ranging market
        }
    elif 'ranging_low_volatility' in market_regime:
        # Strategi khusus untuk market ranging dengan volatilitas rendah
        weights = {
            'rsi': 0.20,         # RSI penting
            'macd': 0.15,        # MACD untuk perubahan momentum
            'bollinger': 0.30,   # Bollinger sangat penting untuk boundaries
            'stochastic': 0.15,  # Stochastic untuk overbought/oversold
            'adx': 0.05,         # ADX kurang relevan
            'moving_avg': 0.15   # MA untuk dukungan/resistensi
        }
    
    # Hitung weighted scores
    buy_score = 0
    sell_score = 0
    total_weight = 0
    used_signals = []
    
    for indicator, signal_info in signals.items():
        if indicator in weights and 'signal' in signal_info:
            weight = weights[indicator]
            signal = signal_info['signal']
            strength = signal_info.get('strength', 0.5)
            
            total_weight += weight
            used_signals.append(indicator)
            
            if signal == 'buy':
                buy_score += strength * weight
            elif signal == 'sell':
                sell_score += strength * weight
            # 'hold' tidak berkontribusi ke buy_score atau sell_score
    
    # Jika tidak ada sinyal yang ditemukan, set nilai default
    if total_weight == 0:
        logger.warning("Tidak ada sinyal valid yang ditemukan untuk ensemble")
        return {
            "action": "hold",
            "confidence": 0.5,
            "buy_score": 0,
            "sell_score": 0,
            "used_signals": []
        }
    
    # Normalisasi skor
    buy_score = buy_score / total_weight 
    sell_score = sell_score / total_weight
    
    # Tentukan aksi berdasarkan skor tertinggi
    threshold = 0.2  # Minimum difference untuk keputusan yang jelas
    
    if buy_score > sell_score + threshold:
        action = "buy"
        confidence = buy_score
    elif sell_score > buy_score + threshold:
        action = "sell"
        confidence = sell_score
    else:
        action = "hold"
        # Konfiden hold berdasarkan kedekatan skor buy dan sell
        # Jika keduanya mendekati, konfiden hold tinggi
        confidence = 1.0 - abs(buy_score - sell_score)
    
    # Batas confidence 0-1
    confidence = min(1.0, max(0.0, confidence))
    
    return {
        "action": action,
        "confidence": confidence,
        "buy_score": buy_score,
        "sell_score": sell_score,
        "used_signals": used_signals,
        "weights": {k: v for k, v in weights.items() if k in used_signals}
    }


def calculate_rsi(prices: pd.Series, window: int = 14) -> pd.Series:
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
    """Implementasi RSI menggunakan pandas yang diperbarui dengan Exponential Average"""
    # Calculate price changes
    delta = prices.diff()
    
    # Separate gains and losses
    gain = delta.where(delta > 0, 0)
    loss = -delta.where(delta < 0, 0)
    
    # Calculate averages - gunakan EMA untuk hasil yang lebih responsif
    # First values using SMA
    avg_gain = gain.iloc[:window].mean()
    avg_loss = loss.iloc[:window].mean()
    
    # Rest of the values - smoother RSI dengan formula Wilder
    rsi_values = [np.nan] * window
    for i in range(window, len(prices)):
        avg_gain = (avg_gain * (window - 1) + gain.iloc[i]) / window
        avg_loss = (avg_loss * (window - 1) + loss.iloc[i]) / window
        
        # Hindari division by zero
        if avg_loss == 0:
            rsi_values.append(100)
        else:
            rs = avg_gain / avg_loss
            rsi_values.append(100 - (100 / (1 + rs)))
    
    return pd.Series(rsi_values, index=prices.index)


def calculate_macd(prices: pd.Series, 
                  fast_period: int = 12, 
                  slow_period: int = 26, 
                  signal_period: int = 9) -> Tuple[pd.Series, pd.Series, pd.Series]:
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
    """Implementasi MACD menggunakan pandas yang diperbarui untuk akurasi yang lebih baik"""
    # Versi yang lebih akurat dengan adjust=True
    fast_ema = prices.ewm(span=fast_period, adjust=True, min_periods=fast_period).mean()
    slow_ema = prices.ewm(span=slow_period, adjust=True, min_periods=slow_period).mean()
    
    macd = fast_ema - slow_ema
    signal = macd.ewm(span=signal_period, adjust=True, min_periods=signal_period).mean()
    histogram = macd - signal
    
    return macd, signal, histogram


def calculate_bollinger_bands(prices: pd.Series, window: int = 20, num_std: float = 2.0) -> Tuple[pd.Series, pd.Series, pd.Series]:
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
    # Gunakan SMA yang sebenarnya untuk konsistensi
    middle = prices.rolling(window=window, min_periods=window).mean()
    std = prices.rolling(window=window, min_periods=window).std()
    
    upper = middle + (std * num_std)
    lower = middle - (std * num_std)
    
    return upper, middle, lower


def calculate_stochastic(prices: pd.Series, 
                        high_prices: pd.Series, 
                        low_prices: pd.Series,
                        k_period: int = 14, 
                        d_period: int = 3) -> Tuple[pd.Series, pd.Series]:
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
    """Implementasi Stochastic Oscillator menggunakan pandas yang diperbarui"""
    # Calculate Stochastic Oscillator dengan implementasi yang lebih akurat
    # Stochastic %K = (Current Close - Lowest Low) / (Highest High - Lowest Low) * 100
    
    # Hitung range k_period
    lowest_low = low_prices.rolling(window=k_period, min_periods=k_period).min()
    highest_high = high_prices.rolling(window=k_period, min_periods=k_period).max()
    
    # Hindari pembagian dengan nol
    denominator = highest_high - lowest_low
    denominator = denominator.replace(0, np.finfo(float).eps)
    
    # Hitung %K dengan min/max capping
    k_raw = ((prices - lowest_low) / denominator) * 100
    k = k_raw.clip(0, 100)  # Pastikan nilainya dalam range 0-100
    
    # Hitung %D (simple moving average dari %K)
    d = k.rolling(window=d_period, min_periods=d_period).mean()
    
    return k, d


def calculate_adx(high_prices: pd.Series, 
                low_prices: pd.Series, 
                close_prices: pd.Series, 
                window: int = 14) -> Tuple[pd.Series, pd.Series, pd.Series]:
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
    """Implementasi ADX menggunakan pandas yang diperbarui"""
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
        atr = true_range.ewm(span=window, min_periods=window).mean()  # EMA for Wilder's smoothing
        
        # 2. Directional Movement
        up_move = high_prices - high_prices.shift(1)
        down_move = low_prices.shift(1) - low_prices
        
        # Handle NaN values
        up_move = up_move.fillna(0)
        down_move = down_move.fillna(0)
        
        # Plus Directional Movement (+DM)
        plus_dm = np.where((up_move > down_move) & (up_move > 0), up_move, 0)
        # Negative Directional Movement (-DM)
        minus_dm = np.where((down_move > up_move) & (down_move > 0), down_move, 0)
        
        # Convert to Series
        plus_dm = pd.Series(plus_dm, index=close_prices.index)
        minus_dm = pd.Series(minus_dm, index=close_prices.index)
        
        # 3. Smoothed Directional Movement
        # Wilder's smoothing (similar to EMA with alpha=1/period)
        smooth_plus_dm = plus_dm.ewm(span=window, min_periods=window).mean()
        smooth_minus_dm = minus_dm.ewm(span=window, min_periods=window).mean()
        
        # Ensure ATR is not zero
        atr_safe = atr.replace(0, np.finfo(float).eps)
        
        # 4. Directional Indicators
        plus_di = 100 * (smooth_plus_dm / atr_safe)
        minus_di = 100 * (smooth_minus_dm / atr_safe)
        
        # 5. Directional Index (DX)
        di_diff = (plus_di - minus_di).abs()
        di_sum = plus_di + minus_di
        
        # Avoid division by zero
        di_sum_safe = di_sum.replace(0, np.finfo(float).eps)
        dx = 100 * (di_diff / di_sum_safe)
        
        # 6. Average Directional Index (ADX)
        adx = dx.ewm(span=window, min_periods=window).mean()
        
        return adx, plus_di, minus_di
    except Exception as e:
        logger.error(f"Error dalam perhitungan ADX pandas: {str(e)}")
        # Return empty series if calculation fails
        empty_series = pd.Series(np.nan, index=close_prices.index)
        return empty_series, empty_series, empty_series


def calculate_atr(high_prices: pd.Series, 
                low_prices: pd.Series, 
                close_prices: pd.Series, 
                window: int = 14) -> pd.Series:
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
    """Implementasi ATR menggunakan pandas yang diperbarui"""
    # Calculate ATR using pandas with Wilder's smoothing for better accuracy
    prev_close = close_prices.shift(1)
    tr1 = high_prices - low_prices  # High - Low
    tr2 = (high_prices - prev_close).abs()  # |High - Previous Close|
    tr3 = (low_prices - prev_close).abs()  # |Low - Previous Close|
    
    # Handle NaN values in each component
    tr1 = tr1.fillna(0)
    tr2 = tr2.fillna(0)
    tr3 = tr3.fillna(0)
    
    true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
    
    # Gunakan Exponential Moving Average (EMA) dengan factor 1/n untuk hasil yang sama dengan Wilder
    # Wilder's smoothing formula equivalen dengan EMA dengan alpha = 1/n
    # Menggunakan span = 2*window-1 untuk EMA supaya setara dengan Wilder's smoothing
    atr = true_range.ewm(alpha=1/window, min_periods=window, adjust=False).mean()
    
    return atr


def calculate_ichimoku(prices_df: pd.DataFrame, 
                      conversion_period: int = 9, 
                      base_period: int = 26,
                      span_b_period: int = 52, 
                      displacement_period: int = 26) -> Dict[str, pd.Series]:
    # Cek apakah columns diperlukan ada
    required = ['high', 'low', 'close']
    for col in required:
        if col not in prices_df.columns:
            logger.warning(f"Column {col} tidak tersedia untuk kalkulasi Ichimoku Cloud")
            return {}  # Return empty dict if required columns not available

    # Extract high, low, close
    high = prices_df['high']
    low = prices_df['low']
    close = prices_df['close']
    
    # Calculate Tenkan-sen (Conversion Line)
    tenkan_high = high.rolling(window=conversion_period).max()
    tenkan_low = low.rolling(window=conversion_period).min()
    tenkan = (tenkan_high + tenkan_low) / 2
    
    # Calculate Kijun-sen (Base Line)
    kijun_high = high.rolling(window=base_period).max()
    kijun_low = low.rolling(window=base_period).min()
    kijun = (kijun_high + kijun_low) / 2
    
    # Calculate Senkou Span A (Leading Span A)
    senkou_a = ((tenkan + kijun) / 2).shift(displacement_period)
    
    # Calculate Senkou Span B (Leading Span B)
    senkou_b_high = high.rolling(window=span_b_period).max()
    senkou_b_low = low.rolling(window=span_b_period).min()
    senkou_b = ((senkou_b_high + senkou_b_low) / 2).shift(displacement_period)
    
    # Calculate Chikou Span (Lagging Span)
    chikou = close.shift(-displacement_period)
    
    return {
        'tenkan': tenkan,
        'kijun': kijun,
        'senkou_a': senkou_a,
        'senkou_b': senkou_b,
        'chikou': chikou
    }


def backtest_strategy(prices_df: pd.DataFrame, 
                    strategy_type: str = 'macd', 
                    indicator_periods: Optional[Dict[str, Any]] = None,
                    initial_capital: float = 10000.0) -> Dict[str, Any]:
    # Cek apakah data cukup
    if len(prices_df) < 50:
        logger.warning("Tidak cukup data untuk backtest. Minimal 50 titik data diperlukan.")
        return {"error": "Insufficient data for backtesting"}
    
    # Copy data agar tidak mengubah original
    df = prices_df.copy()
    
    # Tambahkan indikator sesuai strategi
    if strategy_type == 'macd':
        macd_fast = indicator_periods.get('macd_fast', 12) if indicator_periods else 12
        macd_slow = indicator_periods.get('macd_slow', 26) if indicator_periods else 26
        macd_signal = indicator_periods.get('macd_signal', 9) if indicator_periods else 9
        
        macd, signal, hist = calculate_macd(df['close'], macd_fast, macd_slow, macd_signal)
        
        df['macd'] = macd
        df['macd_signal'] = signal
        df['macd_hist'] = hist
        df['position'] = 0
        
        # Generate signals
        df.loc[macd > signal, 'position'] = 1  # Buy signal
        df.loc[macd < signal, 'position'] = -1  # Sell signal
        
    elif strategy_type == 'rsi':
        rsi_period = indicator_periods.get('rsi_period', 14) if indicator_periods else 14
        rsi_overbought = indicator_periods.get('rsi_overbought', 70) if indicator_periods else 70
        rsi_oversold = indicator_periods.get('rsi_oversold', 30) if indicator_periods else 30
        
        df['rsi'] = calculate_rsi(df['close'], rsi_period)
        df['position'] = 0
        
        # Generate signals - buy when oversold, sell when overbought
        df.loc[df['rsi'] < rsi_oversold, 'position'] = 1  # Buy signal
        df.loc[df['rsi'] > rsi_overbought, 'position'] = -1  # Sell signal
        
    elif strategy_type == 'bollinger':
        bb_period = indicator_periods.get('bb_period', 20) if indicator_periods else 20
        bb_std = indicator_periods.get('bb_std', 2.0) if indicator_periods else 2.0
        
        upper, middle, lower = calculate_bollinger_bands(df['close'], bb_period, bb_std)
        
        df['bb_upper'] = upper
        df['bb_middle'] = middle
        df['bb_lower'] = lower
        df['position'] = 0
        
        # Buy when price touches lower band, sell when it touches upper band
        df.loc[df['close'] <= df['bb_lower'], 'position'] = 1
        df.loc[df['close'] >= df['bb_upper'], 'position'] = -1
        
    else:
        # Default strategy: simple moving average crossover
        short_ma = indicator_periods.get('ma_short', 20) if indicator_periods else 20
        long_ma = indicator_periods.get('ma_medium', 50) if indicator_periods else 50
        
        df['short_ma'] = df['close'].rolling(window=short_ma).mean()
        df['long_ma'] = df['close'].rolling(window=long_ma).mean()
        df['position'] = 0
        
        # Buy when short MA crosses above long MA, sell when it crosses below
        df.loc[df['short_ma'] > df['long_ma'], 'position'] = 1
        df.loc[df['short_ma'] < df['long_ma'], 'position'] = -1
    
    # Hitung perubahan posisi untuk trade signals
    df['position_change'] = df['position'].diff()
    
    # Simulasi trading
    df['returns'] = df['close'].pct_change()
    df['strategy_returns'] = df['position'].shift(1) * df['returns']
    
    # Hapus NaN di awal
    df = df.dropna()
    
    # Hitung metrik
    capital = initial_capital
    holdings = 0
    trades = []
    entries = []
    exits = []
    
    for i, row in df.iterrows():
        if row['position_change'] > 0:  # Enter long position
            entry_price = row['close']
            entry_date = i
            holdings = capital / entry_price
            entries.append((entry_date, entry_price))
            trades.append({
                'type': 'buy',
                'date': entry_date,
                'price': entry_price,
                'holdings': holdings,
                'capital': capital
            })
        elif row['position_change'] < 0 and holdings > 0:  # Exit long position
            exit_price = row['close']
            exit_date = i
            capital = holdings * exit_price
            exits.append((exit_date, exit_price))
            trades.append({
                'type': 'sell',
                'date': exit_date,
                'price': exit_price,
                'holdings': 0,
                'capital': capital
            })
            holdings = 0
    
    # Hitung hasil akhir
    final_capital = capital if holdings == 0 else holdings * df['close'].iloc[-1]
    total_return = (final_capital - initial_capital) / initial_capital
    annual_return = total_return * (252 / len(df))  # Annualized return
    
    # Hitung drawdown
    df['cumulative_returns'] = (1 + df['strategy_returns']).cumprod()
    df['cumulative_max'] = df['cumulative_returns'].cummax()
    df['drawdown'] = (df['cumulative_max'] - df['cumulative_returns']) / df['cumulative_max']
    
    max_drawdown = df['drawdown'].max()
    
    # Hitung Sharpe ratio (annualized, assuming risk-free rate of 0)
    sharpe_ratio = np.sqrt(252) * df['strategy_returns'].mean() / df['strategy_returns'].std() if df['strategy_returns'].std() != 0 else 0
    
    # Hitung win rate
    if len(trades) < 2:
        win_rate = 0
    else:
        wins = 0
        for i in range(1, len(trades), 2):
            if i < len(trades):
                if trades[i]['capital'] > trades[i-1]['capital']:
                    wins += 1
        win_rate = wins / ((len(trades) // 2) or 1)  # Avoid division by zero
    
    return {
        'total_return': total_return,
        'annual_return': annual_return,
        'max_drawdown': max_drawdown,
        'sharpe_ratio': sharpe_ratio,
        'win_rate': win_rate,
        'num_trades': len(trades) // 2,  # Bagi 2 karena setiap trade = entry + exit
        'trades': trades,
        'returns_series': df['strategy_returns'],
        'cumulative_returns': df['cumulative_returns'],
        'drawdown_series': df['drawdown']
    }


def generate_trading_signals(prices_df: pd.DataFrame, 
                         indicator_periods: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    logger.info("Generating trading signals")
    
    # Membuat salinan data untuk menghindari modifikasi pada input asli
    df = prices_df.copy()
    
    # Default period jika tidak ada yang disediakan
    if indicator_periods is None:
        indicator_periods = {}
    
    # Deteksi market regime dengan fallback untuk data terbatas
    market_regime = detect_market_regime(prices_df)
    logger.info(f"Detected market regime: {market_regime}")
    
    # Jika tidak ada periode yang diberikan, tentukan yang optimal untuk regime ini
    if not indicator_periods:
        indicator_periods = get_optimal_parameters(df, market_regime)
        logger.info(f"Using optimized parameters for {market_regime}")
    
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
    
    # Hitung minimal data yang diperlukan berdasarkan indikator terlama
    min_required_points = max(
        3 * rsi_period,
        macd_slow + macd_signal + 10,
        bb_period + 10,
        stoch_k + stoch_d + 5,
        ma_long + 10
    )
    
    # Cek apakah ada cukup data untuk jenis analisis
    is_short_term = ma_long <= 100
    if is_short_term:
        min_data_needed = 60  # Data minimal untuk analisis jangka pendek
        logger.info(f"Analisis jangka pendek terdeteksi, kebutuhan data minimum disesuaikan menjadi {min_data_needed}")
    else:
        # Minimum data untuk analisis jangka panjang
        min_data_needed = max(
            3 * rsi_period,
            macd_slow + macd_signal + 10,
            bb_period + 10,
            stoch_k + stoch_d + 5,
            ma_long + 10
        )
    
    # Menyesuaikan parameter jika data tidak cukup
    if len(df) < min_data_needed:
        # Turunkan periode MA jangka panjang secara proporsional
        original_ma_long = ma_long
        ma_long = min(original_ma_long, len(df) // 3)
        indicator_periods['ma_long'] = ma_long
        logger.warning(f"Menyesuaikan periode MA jangka panjang dari {original_ma_long} menjadi {ma_long} karena data terbatas")
    
    # Gunakan class TechnicalIndicators untuk menghitung semua indikator sekaligus
    # dengan pendekatan yang menghindari fragmentasi
    from src.technical.indicators import TechnicalIndicators
    
    ti = TechnicalIndicators(df, indicator_periods)
    df_with_indicators = ti.add_indicators()
    
    # Ambil data terbaru
    latest_data = df_with_indicators.iloc[-1].to_dict()

    # Use appropriate column names or defaults
    close_col = 'close'
    high_col = 'high' if 'high' in prices_df.columns else close_col
    low_col = 'low' if 'low' in prices_df.columns else close_col
    
    # Extract price series
    close_prices = prices_df[close_col]
    high_prices = prices_df[high_col]
    low_prices = prices_df[low_col]
    
    # Dictionary untuk menyimpan hasil perhitungan indikator
    indicators_result = {}
    
    # Flag untuk pelacak indikator yang berhasil dihitung
    valid_indicators = {'rsi': False, 'macd': False, 'bb': False, 'stoch': False, 'adx': False, 'ma': False}
    
    # 1. RSI dengan penanganan error
    if len(close_prices) >= rsi_period + 1:
        try:
            rsi = calculate_rsi(close_prices, window=rsi_period)
            valid_indicators['rsi'] = not rsi.isnull().all()
            indicators_result['rsi'] = rsi
        except Exception as e:
            logger.error(f"Error calculating RSI: {str(e)}")
            # Gunakan NaN untuk RSI
            indicators_result['rsi'] = pd.Series(np.nan, index=close_prices.index)
    else:
        logger.warning(f"Insufficient data for RSI calculation. Need {rsi_period + 1}, have {len(close_prices)}")
        indicators_result['rsi'] = pd.Series(np.nan, index=close_prices.index)
    
    # 2. MACD dengan penanganan error
    try:
        if len(close_prices) >= macd_slow + macd_signal:
            macd, signal, histogram = calculate_macd(close_prices, 
                                                 fast_period=macd_fast, 
                                                 slow_period=macd_slow, 
                                                 signal_period=macd_signal)
            
            valid_indicators['macd'] = not (macd.isnull().all() or signal.isnull().all())
            indicators_result['macd'] = macd
            indicators_result['macd_signal'] = signal
            indicators_result['macd_hist'] = histogram
        else:
            logger.warning(f"Tidak cukup data untuk menghitung MACD dengan benar. Minimal {macd_slow + macd_signal} titik data diperlukan.")
            # Buat MACD kosong dengan NaN
            empty_series = pd.Series(np.nan, index=close_prices.index)
            indicators_result['macd'] = empty_series
            indicators_result['macd_signal'] = empty_series
            indicators_result['macd_hist'] = empty_series
    except Exception as e:
        logger.error(f"Error calculating MACD: {str(e)}")
        # Buat MACD kosong dengan NaN
        empty_series = pd.Series(np.nan, index=close_prices.index)
        indicators_result['macd'] = empty_series
        indicators_result['macd_signal'] = empty_series
        indicators_result['macd_hist'] = empty_series
    
    # 3. Bollinger Bands dengan penanganan error
    try:
        if len(close_prices) >= bb_period:
            upper_band, middle_band, lower_band = calculate_bollinger_bands(close_prices, window=bb_period)
            
            valid_indicators['bb'] = not (upper_band.isnull().all() or middle_band.isnull().all() or lower_band.isnull().all())
            indicators_result['bb_upper'] = upper_band
            indicators_result['bb_middle'] = middle_band
            indicators_result['bb_lower'] = lower_band
        else:
            logger.warning(f"Insufficient data for Bollinger Bands calculation. Need {bb_period}, have {len(close_prices)}")
            # Buat Bollinger Bands kosong dengan NaN
            empty_series = pd.Series(np.nan, index=close_prices.index)
            indicators_result['bb_upper'] = empty_series
            indicators_result['bb_middle'] = empty_series
            indicators_result['bb_lower'] = empty_series
    except Exception as e:
        logger.error(f"Error calculating Bollinger Bands: {str(e)}")
        # Buat Bollinger Bands kosong dengan NaN
        empty_series = pd.Series(np.nan, index=close_prices.index)
        indicators_result['bb_upper'] = empty_series
        indicators_result['bb_middle'] = empty_series
        indicators_result['bb_lower'] = empty_series
    
    # 4. Stochastic Oscillator dengan penanganan error
    try:
        if len(close_prices) >= stoch_k + stoch_d:
            k, d = calculate_stochastic(close_prices, high_prices, low_prices, 
                                     k_period=stoch_k, d_period=stoch_d)
            
            valid_indicators['stoch'] = not (k.isnull().all() or d.isnull().all())
            indicators_result['stoch_k'] = k
            indicators_result['stoch_d'] = d
        else:
            logger.warning(f"Insufficient data for Stochastic calculation. Need {stoch_k + stoch_d}, have {len(close_prices)}")
            # Buat Stochastic kosong dengan NaN
            empty_series = pd.Series(np.nan, index=close_prices.index)
            indicators_result['stoch_k'] = empty_series
            indicators_result['stoch_d'] = empty_series
    except Exception as e:
        logger.error(f"Error calculating Stochastic: {str(e)}")
        # Buat Stochastic kosong dengan NaN
        empty_series = pd.Series(np.nan, index=close_prices.index)
        indicators_result['stoch_k'] = empty_series
        indicators_result['stoch_d'] = empty_series
    
    # 5. ADX dengan penanganan error
    try:
        if len(close_prices) >= 2 * 14:
            adx, plus_di, minus_di = calculate_adx(high_prices, low_prices, close_prices)
            
            valid_indicators['adx'] = not (adx.isnull().all() or plus_di.isnull().all() or minus_di.isnull().all())
            indicators_result['adx'] = adx
            indicators_result['plus_di'] = plus_di
            indicators_result['minus_di'] = minus_di
        else:
            logger.warning(f"Tidak cukup data untuk menghitung ADX. Minimal {2*14} titik data diperlukan.")
            # Buat ADX kosong dengan NaN atau nilai default
            indicators_result['adx'] = pd.Series(15.0, index=close_prices.index)  # Nilai default - weak trend
            indicators_result['plus_di'] = pd.Series(20.0, index=close_prices.index)
            indicators_result['minus_di'] = pd.Series(20.0, index=close_prices.index)
    except Exception as e:
        logger.error(f"Error calculating ADX: {str(e)}")
        # Buat ADX kosong dengan NaN atau nilai default
        indicators_result['adx'] = pd.Series(15.0, index=close_prices.index)  # Nilai default - weak trend
        indicators_result['plus_di'] = pd.Series(20.0, index=close_prices.index) 
        indicators_result['minus_di'] = pd.Series(20.0, index=close_prices.index)
    
    # 6. Moving Averages dengan penanganan error
    try:
        # Hitung MA hanya jika panjang data cukup
        if len(close_prices) >= ma_short:
            indicators_result['sma_short'] = close_prices.rolling(window=ma_short).mean()
            valid_indicators['ma'] = True
        else:
            # Buat SMA kosong
            indicators_result['sma_short'] = pd.Series(np.nan, index=close_prices.index)
            
        if len(close_prices) >= ma_medium:
            indicators_result['sma_medium'] = close_prices.rolling(window=ma_medium).mean()
        else:
            # Buat SMA kosong
            indicators_result['sma_medium'] = pd.Series(np.nan, index=close_prices.index)
            
        if len(close_prices) >= ma_long:
            indicators_result['sma_long'] = close_prices.rolling(window=ma_long).mean()
        else:
            # Buat SMA kosong
            indicators_result['sma_long'] = pd.Series(np.nan, index=close_prices.index)
            
    except Exception as e:
        logger.error(f"Error calculating Moving Averages: {str(e)}")
        # Buat MA kosong
        indicators_result['sma_short'] = pd.Series(np.nan, index=close_prices.index)
        indicators_result['sma_medium'] = pd.Series(np.nan, index=close_prices.index)
        indicators_result['sma_long'] = pd.Series(np.nan, index=close_prices.index)
    
    # Ichimoku Cloud sebagai indikator tambahan (opsional)
    try:
        if len(close_prices) >= 52:  # Minimal untuk Ichimoku
            ichimoku = calculate_ichimoku(prices_df)
            if ichimoku:
                indicators_result.update(ichimoku)
        else:
            logger.info(f"Insufficient data for Ichimoku Cloud. Need 52+ data points")
    except Exception as e:
        logger.error(f"Error calculating Ichimoku Cloud: {str(e)}")
    
    # Log status perhitungan indikator
    for indicator_name, valid in valid_indicators.items():
        logger.info(f"Indicator {indicator_name} calculation status: {'Valid' if valid else 'Invalid'}")
    
    # Get latest values dengan safety check untuk NaN
    try:
        latest_close = close_prices.iloc[-1]
        
        latest_rsi = indicators_result['rsi'].iloc[-1] if 'rsi' in indicators_result and not pd.isna(indicators_result['rsi'].iloc[-1]) else 50
        latest_macd = indicators_result['macd'].iloc[-1] if 'macd' in indicators_result and not pd.isna(indicators_result['macd'].iloc[-1]) else 0
        latest_signal = indicators_result['macd_signal'].iloc[-1] if 'macd_signal' in indicators_result and not pd.isna(indicators_result['macd_signal'].iloc[-1]) else 0
        latest_histogram = indicators_result['macd_hist'].iloc[-1] if 'macd_hist' in indicators_result and not pd.isna(indicators_result['macd_hist'].iloc[-1]) else 0
        
        latest_upper = indicators_result['bb_upper'].iloc[-1] if 'bb_upper' in indicators_result and not pd.isna(indicators_result['bb_upper'].iloc[-1]) else latest_close * 1.05
        latest_middle = indicators_result['bb_middle'].iloc[-1] if 'bb_middle' in indicators_result and not pd.isna(indicators_result['bb_middle'].iloc[-1]) else latest_close
        latest_lower = indicators_result['bb_lower'].iloc[-1] if 'bb_lower' in indicators_result and not pd.isna(indicators_result['bb_lower'].iloc[-1]) else latest_close * 0.95
        
        latest_k = indicators_result['stoch_k'].iloc[-1] if 'stoch_k' in indicators_result and not pd.isna(indicators_result['stoch_k'].iloc[-1]) else 50
        latest_d = indicators_result['stoch_d'].iloc[-1] if 'stoch_d' in indicators_result and not pd.isna(indicators_result['stoch_d'].iloc[-1]) else 50
        
        latest_adx = indicators_result['adx'].iloc[-1] if 'adx' in indicators_result and not pd.isna(indicators_result['adx'].iloc[-1]) else 15
        latest_plus_di = indicators_result['plus_di'].iloc[-1] if 'plus_di' in indicators_result and not pd.isna(indicators_result['plus_di'].iloc[-1]) else 20
        latest_minus_di = indicators_result['minus_di'].iloc[-1] if 'minus_di' in indicators_result and not pd.isna(indicators_result['minus_di'].iloc[-1]) else 20
        
        latest_sma_short = indicators_result['sma_short'].iloc[-1] if 'sma_short' in indicators_result and not pd.isna(indicators_result['sma_short'].iloc[-1]) else latest_close
        latest_sma_medium = indicators_result['sma_medium'].iloc[-1] if 'sma_medium' in indicators_result and not pd.isna(indicators_result['sma_medium'].iloc[-1]) else latest_close
        latest_sma_long = indicators_result['sma_long'].iloc[-1] if 'sma_long' in indicators_result and not pd.isna(indicators_result['sma_long'].iloc[-1]) else latest_close
    
    except Exception as e:
        logger.error(f"Error extracting latest indicator values: {str(e)}")
        # Fallback to safe default values
        latest_close = close_prices.iloc[-1] if not close_prices.empty else 0
        latest_rsi = 50
        latest_macd = 0
        latest_signal = 0
        latest_histogram = 0
        latest_upper = latest_close * 1.05
        latest_middle = latest_close
        latest_lower = latest_close * 0.95
        latest_k = 50
        latest_d = 50
        latest_adx = 15
        latest_plus_di = 20
        latest_minus_di = 20
        latest_sma_short = latest_close
        latest_sma_medium = latest_close
        latest_sma_long = latest_close
    
    # Ichimoku latest values dengan try/except
    try:
        if 'tenkan' in indicators_result and 'kijun' in indicators_result:
            latest_tenkan = indicators_result['tenkan'].iloc[-1] if not pd.isna(indicators_result['tenkan'].iloc[-1]) else latest_close
            latest_kijun = indicators_result['kijun'].iloc[-1] if not pd.isna(indicators_result['kijun'].iloc[-1]) else latest_close
            
            latest_senkou_a = indicators_result['senkou_a'].iloc[-1] if not pd.isna(indicators_result['senkou_a'].iloc[-1]) else latest_close
            latest_senkou_b = indicators_result['senkou_b'].iloc[-1] if not pd.isna(indicators_result['senkou_b'].iloc[-1]) else latest_close
    except Exception as e:
        logger.error(f"Error extracting Ichimoku values: {str(e)}")
        # Tidak perlu mengisi fallback values, karena ichimoku opsional
    
    # Calculate signals
    signals = {}
    
    # RSI signals
    if valid_indicators['rsi']:
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
            # Dalam range menengah, tentukan berdasarkan trend RSI
            try:
                rsi_slope = indicators_result['rsi'].diff(3).iloc[-1] if len(indicators_result['rsi']) > 3 else 0
                
                if rsi_slope > 3 and latest_rsi > 50:  # RSI meningkat dan di atas 50
                    signals['rsi'] = {
                        'signal': 'buy',
                        'strength': 0.5 + min(0.3, abs(rsi_slope) / 10),
                        'description': f"RSI meningkat di level {latest_rsi:.2f} (periode {rsi_period})"
                    }
                elif rsi_slope < -3 and latest_rsi < 50:  # RSI menurun dan di bawah 50
                    signals['rsi'] = {
                        'signal': 'sell',
                        'strength': 0.5 + min(0.3, abs(rsi_slope) / 10),
                        'description': f"RSI menurun di level {latest_rsi:.2f} (periode {rsi_period})"
                    }
                else:
                    signals['rsi'] = {
                        'signal': 'hold',
                        'strength': 0.5,
                        'description': f"RSI netral di level {latest_rsi:.2f} (periode {rsi_period})"
                    }
            except Exception as e:
                logger.error(f"Error calculating RSI slope: {str(e)}")
                signals['rsi'] = {
                    'signal': 'hold',
                    'strength': 0.5,
                    'description': f"RSI netral di level {latest_rsi:.2f} (periode {rsi_period})"
                }
    else:
        signals['rsi'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"RSI netral di level {latest_rsi:.2f} (periode {rsi_period})"
        }
    
    # MACD signals - perbaiki pengecekan crossover
    if valid_indicators['macd']:
        # Pastikan kita memiliki setidaknya 2 data poin valid
        try:
            valid_macd = indicators_result['macd'].dropna()
            valid_signal = indicators_result['macd_signal'].dropna()
            
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
                # Periksa juga momentum MACD
                macd_slope = indicators_result['macd'].diff(3).iloc[-1] if len(indicators_result['macd']) > 3 else 0
                
                if macd_slope > 0:  # MACD naik
                    signals['macd'] = {
                        'signal': 'buy',
                        'strength': 0.6 + min(0.2, abs(macd_slope) / 100),
                        'description': f"MACD di atas signal line dan meningkat (bullish) - ({macd_fast}/{macd_slow}/{macd_signal})"
                    }
                else:  # MACD turun tapi masih di atas signal
                    signals['macd'] = {
                        'signal': 'buy',
                        'strength': 0.6,
                        'description': f"MACD di atas signal line (bullish) - ({macd_fast}/{macd_slow}/{macd_signal})"
                    }
            elif latest_macd < latest_signal:
                # Periksa juga momentum MACD
                macd_slope = indicators_result['macd'].diff(3).iloc[-1] if len(indicators_result['macd']) > 3 else 0
                
                if macd_slope < 0:  # MACD turun
                    signals['macd'] = {
                        'signal': 'sell',
                        'strength': 0.6 + min(0.2, abs(macd_slope) / 100),
                        'description': f"MACD di bawah signal line dan menurun (bearish) - ({macd_fast}/{macd_slow}/{macd_signal})"
                    }
                else:  # MACD naik tapi masih di bawah signal
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
        except Exception as e:
            logger.error(f"Error calculating MACD signals: {str(e)}")
            signals['macd'] = {
                'signal': 'hold',
                'strength': 0.5,
                'description': f"MACD netral - ({macd_fast}/{macd_slow}/{macd_signal})"
            }
    else:
        signals['macd'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"MACD netral - ({macd_fast}/{macd_slow}/{macd_signal})"
        }
    
    # Bollinger Bands signals - analisis lebih canggih
    # Cek apakah nilai Bollinger Bands valid
    if valid_indicators['bb'] and not pd.isna(latest_upper) and not pd.isna(latest_lower) and latest_upper > latest_lower:
        try:
            bb_percent = (latest_close - latest_lower) / (latest_upper - latest_lower)
            
            # Price velocity (kecepatan perubahan harga)
            price_change = close_prices.pct_change(3).iloc[-1] if len(close_prices) > 3 else 0
            
            if latest_close < latest_lower:
                # Oversold condition - lebih kuat jika harga jatuh cepat
                signals['bollinger'] = {
                    'signal': 'buy',
                    'strength': 0.7 + min(0.2, abs(price_change) * 5) if price_change < 0 else 0.7,
                    'description': f"Harga di bawah lower Bollinger Band (oversold) - (periode {bb_period})"
                }
            elif latest_close > latest_upper:
                # Overbought condition - lebih kuat jika harga naik cepat
                signals['bollinger'] = {
                    'signal': 'sell',
                    'strength': 0.7 + min(0.2, abs(price_change) * 5) if price_change > 0 else 0.7,
                    'description': f"Harga di atas upper Bollinger Band (overbought) - (periode {bb_period})"
                }
            elif bb_percent > 0.8:
                # Mendekati upper band - perhatikan arah
                if price_change > 0:  # Harga masih naik menuju upper band
                    signals['bollinger'] = {
                        'signal': 'hold',
                        'strength': 0.5,
                        'description': f"Harga mendekati upper Bollinger Band (momentum bullish) - (periode {bb_period})"
                    }
                else:  # Harga berbelok turun dari upper band
                    signals['bollinger'] = {
                        'signal': 'sell',
                        'strength': 0.6,
                        'description': f"Harga berbalik dari upper Bollinger Band (potensi pembalikan) - (periode {bb_period})"
                    }
            elif bb_percent < 0.2:
                # Mendekati lower band - perhatikan arah
                if price_change < 0:  # Harga masih turun menuju lower band
                    signals['bollinger'] = {
                        'signal': 'hold',
                        'strength': 0.5,
                        'description': f"Harga mendekati lower Bollinger Band (momentum bearish) - (periode {bb_period})"
                    }
                else:  # Harga berbelok naik dari lower band
                    signals['bollinger'] = {
                        'signal': 'buy',
                        'strength': 0.6,
                        'description': f"Harga berbalik dari lower Bollinger Band (potensi pembalikan) - (periode {bb_period})"
                    }
            else:
                # Middle area
                signals['bollinger'] = {
                    'signal': 'hold',
                    'strength': 0.5,
                    'description': f"Harga di dalam Bollinger Bands (netral) - (periode {bb_period})"
                }
        except Exception as e:
            logger.error(f"Error calculating Bollinger signals: {str(e)}")
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
    
    # Default untuk signal lainnya jika tidak cukup data
    if not valid_indicators['stoch']:
        signals['stochastic'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"Stochastic oscillator netral - ({stoch_k}/{stoch_d})"
        }
        
    if not valid_indicators['adx']:
        signals['adx'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"Tren lemah (ADX: {latest_adx:.2f})"
        }
        
    if not valid_indicators['ma']:
        signals['moving_avg'] = {
            'signal': 'hold',
            'strength': 0.5,
            'description': f"Mixed moving average signals - MA {ma_short}/{ma_medium}/{ma_long}"
        }
    
    # Gunakan weighted_signal_ensemble untuk menghasilkan sinyal gabungan
    ensemble_result = weighted_signal_ensemble(signals, market_regime)
    
    # Arah tren utama berdasarkan regime
    trend_direction = "bullish" if "bullish" in market_regime else "bearish" if "bearish" in market_regime else "neutral"
    
    # Generate target price if applicable
    target_price = None

    if 'high' in prices_df.columns and 'low' in prices_df.columns:
        try:
            atr = calculate_atr(high_prices, low_prices, close_prices)
            latest_atr = atr.iloc[-1] if not pd.isna(atr.iloc[-1]) else (latest_close * 0.02)  # Default to 2% of price
            
            if ensemble_result['action'] == "buy":
                # Target price for buying: berdasarkan regime dan sinyal
                # Use abs() to ensure positive ATR value
                if "trending_bullish" in market_regime:
                    # Dalam tren bullish yang kuat, target lebih tinggi
                    target_price = latest_close + (3 * abs(latest_atr))
                elif "volatile" in market_regime:
                    # Dalam pasar volatil, target lebih konservatif
                    target_price = latest_close + (1.5 * abs(latest_atr))
                else:
                    # Default
                    target_price = latest_close + (2 * abs(latest_atr))
            elif ensemble_result['action'] == "sell":
                # Target price for selling: current price - ATR multiplier
                if "trending_bearish" in market_regime:
                    # Dalam tren bearish yang kuat, target lebih rendah
                    target_price = latest_close - (3 * abs(latest_atr))
                elif "volatile" in market_regime:
                    # Dalam pasar volatil, target lebih konservatif
                    target_price = latest_close - (1.5 * abs(latest_atr))
                else:
                    # Default
                    target_price = latest_close - (2 * abs(latest_atr))
        except Exception as e:
            logger.error(f"Error calculating target price: {str(e)}")
            # Fallback target price
            if ensemble_result['action'] == "buy":
                target_price = latest_close * 1.05  # 5% up
            elif ensemble_result['action'] == "sell":
                target_price = latest_close * 0.95  # 5% down
    
    # Check if confidence meets threshold
    strong_signal = ensemble_result['confidence'] >= CONFIDENCE_THRESHOLD
    
    # Calculate Bollinger Percent B untuk API response
    if pd.isna(latest_upper) or pd.isna(latest_lower) or latest_upper == latest_lower:
        bb_percent_b = 0.5  # Default ke nilai netral
    else:
        bb_percent_b = (latest_close - latest_lower) / (latest_upper - latest_lower)
    
    # Create evidence list - diurutkan berdasarkan sinyal yang dihasilkan
    evidence = []
    
    # Tambahkan bukti yang mendukung sinyal hasil ensemble
    for indicator, signal_info in signals.items():
        if signal_info['signal'] == ensemble_result['action']:
            evidence.append(signal_info['description'])
    
    # Tambahkan bukti netral
    for indicator, signal_info in signals.items():
        if signal_info['signal'] == 'hold' and len(evidence) < 5:
            evidence.append(signal_info['description'])
    
    # Tambahkan bukti yang bertentangan (jika evidence masih sedikit)
    if len(evidence) < 3:
        for indicator, signal_info in signals.items():
            if signal_info['signal'] != ensemble_result['action'] and signal_info['signal'] != 'hold':
                evidence.append(signal_info['description'])
                if len(evidence) >= 5:
                    break
    
    # Jika masih tidak ada bukti, tambahkan message default berdasarkan market regime
    if not evidence:
        evidence.append(f"Market regime saat ini: {market_regime.replace('_', ' ')}")
        evidence.append(f"Jumlah data terbatas ({len(prices_df)} titik data), rekomendasi kurang akurat")
    
    # Add support dan target values dinamis
    target_2 = None
    support_1 = None 
    support_2 = None
    
    # Hitung level support dan resistance jika data cukup
    if len(prices_df) >= 14:
        try:
            # Range harga terbaru
            recent_high = prices_df['high'].iloc[-14:].max() if 'high' in prices_df.columns else prices_df['close'].iloc[-14:].max()
            recent_low = prices_df['low'].iloc[-14:].min() if 'low' in prices_df.columns else prices_df['close'].iloc[-14:].min()
            
            price_range = recent_high - recent_low
            current_price = latest_close
            
            # Fibonacci retracement levels
            if ensemble_result['action'] == "buy":
                # For buy signals (expecting upward movement)
                target_2 = current_price + price_range * 0.618  # Strong target (61.8% extension)
                support_1 = current_price - price_range * 0.236  # Near support
                support_2 = current_price - price_range * 0.382  # Stronger support
            elif ensemble_result['action'] == "sell":
                # For sell signals (expecting downward movement)
                target_2 = current_price - price_range * 0.618  # Strong target (61.8% extension)
                support_1 = current_price + price_range * 0.236  # Near resistance
                support_2 = current_price + price_range * 0.382  # Stronger resistance
            else:
                # For hold signals (use more balanced levels)
                target_2 = current_price + price_range * 0.382
                support_1 = current_price - price_range * 0.236
                support_2 = current_price - price_range * 0.382
        except Exception as e:
            logger.error(f"Error calculating support/resistance levels: {str(e)}")
    
    # Create reversal signals data
    reversal_signals = []
    reversal_probability = 0.0
    
    # Deteksi reversal berdasarkan indikator
    if latest_rsi > 70 and latest_close > latest_upper:
        # Overbought extreme - potential bearish reversal
        reversal_signals.append({
            "type": "extreme_overbought",
            "description": "Extreme overbought conditions (RSI and Bollinger Bands)",
            "strength": 0.75
        })
        reversal_probability = 0.75 if ensemble_result['action'] == 'buy' else 1.0
    elif latest_rsi < 30 and latest_close < latest_lower:
        # Oversold extreme - potential bullish reversal
        reversal_signals.append({
            "type": "extreme_oversold",
            "description": "Extreme oversold conditions (RSI and Bollinger Bands)",
            "strength": 0.75
        })
        reversal_probability = 0.75 if ensemble_result['action'] == 'sell' else 1.0
    elif valid_indicators['stoch']:
        # Get previous K value from the stochastic Series
        stoch_k_series = indicators_result.get('stoch_k', pd.Series())
        if len(stoch_k_series) >= 2:  # Make sure we have enough data points
            prev_k = stoch_k_series.iloc[-2]  # Get previous K value
            if latest_k > 80 and latest_k < prev_k:
                reversal_signals.append({
                    "type": "stoch_overbought_bearish",
                    "description": "Stochastic overbought and turning down (bearish)",
                    "strength": 0.65
                })
                reversal_probability = 0.65 if ensemble_result['action'] == 'buy' else 1.0
    # Dan untuk kasus oversold:
    elif valid_indicators['stoch']:
        stoch_k_series = indicators_result.get('stoch_k', pd.Series())
        if len(stoch_k_series) >= 2:
            prev_k = stoch_k_series.iloc[-2]
            if latest_k < 20 and latest_k > prev_k:
                reversal_signals.append({
                    "type": "stoch_oversold_bullish", 
                    "description": "Stochastic oversold and turning up (bullish)",
                    "strength": 0.65
                })
                reversal_probability = 0.65 if ensemble_result['action'] == 'sell' else 1.0
    
    # Create final recommendation
    recommendation = {
        "action": ensemble_result['action'],
        "confidence": float(ensemble_result['confidence']),
        "strong_signal": strong_signal,
        "evidence": evidence,
        "market_regime": market_regime,
        "trend_direction": trend_direction,
        "buy_score": float(ensemble_result['buy_score']),
        "sell_score": float(ensemble_result['sell_score']),
        "reversal_probability": reversal_probability,
        "reversal_signals": reversal_signals,
        "indicators": {
            "rsi": float(np.nan_to_num(latest_rsi)),
            "macd": float(np.nan_to_num(latest_macd)),
            "macd_signal": float(np.nan_to_num(latest_signal)),
            "macd_histogram": float(np.nan_to_num(latest_histogram)),
            "bollinger_percent": float(np.nan_to_num(bb_percent_b)),
            "stochastic_k": float(np.nan_to_num(latest_k)),
            "stochastic_d": float(np.nan_to_num(latest_d)),
            "adx": float(np.nan_to_num(latest_adx)),
            "plus_di": float(np.nan_to_num(latest_plus_di)),
            "minus_di": float(np.nan_to_num(latest_minus_di))
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
        
    if target_2 is not None and not pd.isna(target_2):
        recommendation["target_2"] = float(target_2)
        
    if support_1 is not None and not pd.isna(support_1):
        recommendation["support_1"] = float(support_1)
        
    if support_2 is not None and not pd.isna(support_2):
        recommendation["support_2"] = float(support_2)
    
    return recommendation

def personalize_signals(signals: Dict[str, Any], 
                      risk_tolerance: str = 'medium') -> Dict[str, Any]:
    # Create a copy to avoid modifying the original
    personalized = signals.copy()
    
    # Jika ada error dalam signals, langsung return
    if 'error' in signals:
        personalized['personalized_message'] = "Tidak dapat membuat sinyal personal: " + signals.get('error', 'Unknown error')
        personalized['risk_profile'] = risk_tolerance
        return personalized
    
    # Default confidence threshold for different risk profiles
    thresholds = {
        'low': 0.75,    # Conservative, needs strong signals
        'medium': 0.6,  # Balanced
        'high': 0.4     # Aggressive, acts on weaker signals
    }
    
    # Apply risk tolerance to confidence
    confidence = signals.get('confidence', 0.5)
    action = signals.get('action', 'hold')
    market_regime = signals.get('market_regime', 'unknown')
    
    # Customize message based on risk profile and market regime
    if risk_tolerance == 'low':
        # Conservative approach
        if action != 'hold' and confidence < thresholds['low']:
            personalized['action'] = 'hold'
            personalized['personalized_message'] = "Sinyal tidak cukup kuat untuk profil risiko konservatif Anda. Tunggu konfirmasi lebih lanjut."
        elif action == 'buy':
            if 'trending_bullish' in market_regime:
                personalized['personalized_message'] = "Sinyal beli terdeteksi dalam tren naik, sesuai dengan strategi konservatif untuk mengikuti tren utama."
            elif 'volatile' in market_regime:
                personalized['action'] = 'hold'
                personalized['personalized_message'] = "Meskipun ada sinyal beli, pasar terlalu volatil untuk profil risiko konservatif Anda."
            else:
                personalized['personalized_message'] = "Sinyal beli dengan keyakinan cukup untuk strategi konservatif Anda."
        elif action == 'sell':
            if 'trending_bearish' in market_regime:
                personalized['personalized_message'] = "Sinyal jual terdeteksi dalam tren turun, sesuai dengan strategi konservatif Anda untuk menghindari kerugian."
            else:
                personalized['personalized_message'] = "Sinyal jual terdeteksi. Dengan profil konservatif, pertimbangkan untuk mengambil keuntungan atau lindungi modal."
        else:  # hold
            personalized['personalized_message'] = "Tetap hold sesuai dengan profil konservatif Anda. Tunggu sinyal yang lebih jelas."
            
    elif risk_tolerance == 'high':
        # Aggressive approach
        # Amplify confidence slightly
        if action != 'hold':
            boosted_confidence = min(1.0, confidence * 1.2)
            personalized['confidence'] = boosted_confidence
            
        if action == 'buy':
            if 'trending_bullish' in market_regime:
                personalized['personalized_message'] = "Sinyal beli kuat dalam tren bullish - cocok untuk strategi agresif Anda."
                personalized['confidence'] = min(1.0, confidence * 1.3)  # Extra confidence boost
            elif 'volatile' in market_regime:
                personalized['personalized_message'] = "Sinyal beli dalam pasar volatil - peluang bagus untuk strategi agresif, tapi tetap perhatikan manajemen risiko."
            else:
                personalized['personalized_message'] = "Sinyal beli terdeteksi - sesuai dengan profil agresif Anda untuk memanfaatkan peluang."
        elif action == 'sell':
            if 'trending_bearish' in market_regime:
                personalized['personalized_message'] = "Sinyal jual kuat dalam tren bearish - sesuai dengan strategi agresif Anda."
                personalized['confidence'] = min(1.0, confidence * 1.3)  # Extra confidence boost
            else:
                personalized['personalized_message'] = "Sinyal jual terdeteksi - pertimbangkan untuk mengambil keuntungan atau bersiap untuk reversal."
        else:  # Original signal is hold
            # For aggressive profiles, try to extract actionable signals even in unclear conditions
            buy_score = signals.get('buy_score', 0)
            sell_score = signals.get('sell_score', 0)
            
            if buy_score > 0.4 and buy_score > sell_score:
                personalized['action'] = 'buy'
                personalized['confidence'] = buy_score
                personalized['personalized_message'] = "Meskipun sinyal utama adalah hold, ada indikasi bullish yang dapat dimanfaatkan dengan strategi agresif Anda."
            elif sell_score > 0.4 and sell_score > buy_score:
                personalized['action'] = 'sell'
                personalized['confidence'] = sell_score
                personalized['personalized_message'] = "Meskipun sinyal utama adalah hold, ada indikasi bearish yang dapat dimanfaatkan dengan strategi agresif Anda."
            else:
                personalized['personalized_message'] = "Tidak ada sinyal yang jelas saat ini, bahkan untuk strategi agresif. Tunggu kondisi pasar yang lebih jelas."
                
    else:  # medium - balanced approach
        if action == 'buy':
            if 'trending_bullish' in market_regime:
                personalized['personalized_message'] = "Sinyal beli yang solid dalam tren bullish - cocok untuk strategi seimbang Anda."
            elif 'volatile' in market_regime:
                personalized['personalized_message'] = "Sinyal beli dalam pasar volatil - pertimbangkan untuk masuk bertahap dengan ukuran posisi yang terukur."
            else:
                personalized['personalized_message'] = "Sinyal beli terdeteksi dengan keyakinan moderat - sesuai dengan profil seimbang Anda."
        elif action == 'sell':
            if 'trending_bearish' in market_regime:
                personalized['personalized_message'] = "Sinyal jual yang solid dalam tren bearish - sesuai dengan strategi seimbang untuk melindungi modal."
            else:
                personalized['personalized_message'] = "Sinyal jual terdeteksi - pertimbangkan untuk mengambil sebagian keuntungan sesuai dengan strategi seimbang."
        else:  # hold
            personalized['personalized_message'] = "Tetap hold - indikator tidak memberikan sinyal yang jelas saat ini."
    
    # Tambahkan saran target price sesuai profil risiko
    if 'target_price' in signals:
        target_price = signals['target_price']
        current_price = signals.get('indicators', {}).get('current_price', 0)
        
        if current_price > 0:
            price_diff_percent = abs(target_price - current_price) / current_price * 100
            
            # Adjust target berdasarkan profil risiko
            if risk_tolerance == 'low':
                # Conservative targets (2/3 of original target)
                adjusted_diff = price_diff_percent * 0.67
                if action == 'buy':
                    adjusted_target = current_price * (1 + adjusted_diff/100)
                else:  # sell
                    adjusted_target = current_price * (1 - adjusted_diff/100)
                    
                personalized['target_price'] = float(adjusted_target)
                personalized['personalized_message'] += f" Target harga yang lebih konservatif: ${adjusted_target:.2f}"
                
            elif risk_tolerance == 'high':
                # Aggressive targets (4/3 of original target)
                adjusted_diff = price_diff_percent * 1.33
                if action == 'buy':
                    adjusted_target = current_price * (1 + adjusted_diff/100)
                else:  # sell
                    adjusted_target = current_price * (1 - adjusted_diff/100)
                    
                personalized['target_price'] = float(adjusted_target)
                personalized['personalized_message'] += f" Target harga yang lebih agresif: ${adjusted_target:.2f}"
                
            else:  # medium - keep original target
                personalized['personalized_message'] += f" Target harga: ${target_price:.2f}"
    
    # Add risk profile info
    personalized['risk_profile'] = risk_tolerance
    
    return personalized


def detect_market_events(prices_df: pd.DataFrame, 
                        window: int = 20, 
                        threshold: float = 2.0,
                        custom_thresholds: Optional[Dict[str, float]] = None) -> Dict[str, Any]:
    # Default thresholds
    thresholds = {
        'pump': threshold,
        'dump': threshold,
        'volatility': threshold,
        'volume_spike': threshold
    }
    
    # Update with custom thresholds if provided
    if custom_thresholds:
        thresholds.update(custom_thresholds)
    
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
    volume_col = 'volume' if 'volume' in prices_df.columns else None
    
    # Extract price series
    close_prices = prices_df[close_col]
    
    # Calculate returns and volatility
    returns = close_prices.pct_change()
    volatility = returns.rolling(window=window).std()
    
    # Calculate moving average and standard deviation
    ma = close_prices.rolling(window=window).mean()
    std = close_prices.rolling(window=window).std()
    
    # Calculate upper and lower bounds for event detection
    upper_bound = ma + (thresholds['pump'] * std)
    lower_bound = ma - (thresholds['dump'] * std)
    
    # Define events
    events = {}
    
    # Pump detection - price significantly above moving average
    events["pump"] = close_prices > upper_bound
    
    # Dump detection - price significantly below moving average
    events["dump"] = close_prices < lower_bound
    
    # High volatility - rolling std of returns exceeds threshold
    avg_volatility = volatility.mean()
    std_volatility = volatility.std()
    events["high_volatility"] = volatility > (avg_volatility + thresholds['volatility'] * std_volatility)
    
    # Volume spike detection if volume data available
    if volume_col and volume_col in prices_df.columns:
        volume = prices_df[volume_col]
        volume_ma = volume.rolling(window=window).mean()
        volume_std = volume.rolling(window=window).std()
        events["volume_spike"] = volume > (volume_ma + thresholds['volume_spike'] * volume_std)
    
    # Market movement patterns
    # Strong movement days (up or down)
    mean_abs_return = returns.abs().mean()
    std_abs_return = returns.abs().std()
    events["strong_movement"] = returns.abs() > (mean_abs_return + 2 * std_abs_return)
    
    # Consecutive days in same direction
    up_days = returns > 0
    down_days = returns < 0
    
    # Consecutive up/down days (3+)
    events["consecutive_up"] = up_days & up_days.shift(1) & up_days.shift(2)
    events["consecutive_down"] = down_days & down_days.shift(1) & down_days.shift(2)
    
    # Count events
    event_counts = {event_type: events[event_type].sum() for event_type in events}
    
    # Get latest event (if any)
    latest_event = "normal"
    
    # Define event priority (which events take precedence)
    event_priority = ["pump", "dump", "high_volatility", "volume_spike", 
                      "consecutive_up", "consecutive_down", "strong_movement"]
    
    # Check for the latest event by priority
    for event_type in event_priority:
        if event_type in events and len(events[event_type]) > 0 and events[event_type].iloc[-1]:
            latest_event = event_type
            break
    
    # Find timestamp of recent events (up to last 5 days)
    recent_events = {}
    lookback = min(5, len(prices_df))
    
    for event_type in events:
        recent_days = []
        for i in range(1, lookback + 1):
            idx = -i
            if idx < -len(events[event_type]):
                break
            if events[event_type].iloc[idx]:
                if isinstance(prices_df.index[idx], pd.Timestamp):
                    recent_days.append(prices_df.index[idx].strftime('%Y-%m-%d'))
                else:
                    recent_days.append(str(prices_df.index[idx]))
        if recent_days:
            recent_events[event_type] = recent_days
    
    return {
        "latest_event": latest_event,
        "event_counts": event_counts,
        "recent_events": recent_events,
        "events": {k: v.astype(int).tolist() for k, v in events.items()}  # Convert to lists for JSON
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
    
    # Detect market regime
    regime = detect_market_regime(df)
    print(f"Detected market regime: {regime}")
    
    # Get optimal parameters for this regime
    optimal_params = get_optimal_parameters(df, regime)
    print("\nOptimal parameters for this regime:")
    for param, value in optimal_params.items():
        print(f"  {param}: {value}")
    
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
    print(f"Market Regime: {signals['market_regime']}")
    
    if 'target_price' in signals:
        print(f"Target Price: ${signals['target_price']:.2f}")
        
    print("\nEvidence:")
    for evidence in signals['evidence']:
        print(f"- {evidence}")
        
    print("\nKey Indicators:")
    for indicator, value in signals['indicators'].items():
        print(f"- {indicator}: {value:.2f}")
        
    # Test ensemble vs simple signals
    print("\n=== Compare Ensemble vs Individual Signals ===")
    print(f"Ensemble Action: {signals['action'].upper()}")
    print(f"Buy Score: {signals['buy_score']:.2f}, Sell Score: {signals['sell_score']:.2f}")
    print("Individual signals:")
    for indicator, signal_info in [
        ('RSI', signals_short.get('rsi', {})),
        ('MACD', signals_short.get('macd', {})),
        ('Bollinger', signals_short.get('bollinger', {}))
    ]:
        if 'signal' in signal_info:
            print(f"- {indicator}: {signal_info['signal'].upper()} (strength: {signal_info.get('strength', 0):.2f})")
    
    # Test personalization
    print("\n=== Personalized Signals ===")
    for risk in ['low', 'medium', 'high']:
        personalized = personalize_signals(signals, risk_tolerance=risk)
        print(f"\nRisk Profile: {risk.upper()}")
        print(f"Action: {personalized['action'].upper()}")
        print(f"Confidence: {personalized['confidence']:.2f}")
        print(f"Message: {personalized['personalized_message']}")
        
    # Test backtest
    print("\n=== Backtest Results ===")
    backtest_results = backtest_strategy(df, 'macd', indicator_periods)
    
    print(f"Total Return: {backtest_results['total_return']:.2%}")
    print(f"Annual Return: {backtest_results['annual_return']:.2%}")
    print(f"Max Drawdown: {backtest_results['max_drawdown']:.2%}")
    print(f"Sharpe Ratio: {backtest_results['sharpe_ratio']:.2f}")
    print(f"Win Rate: {backtest_results['win_rate']:.2%}")
    print(f"Number of Trades: {backtest_results['num_trades']}")