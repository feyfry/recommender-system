"""
Modul untuk ekstraksi dan perhitungan fitur berbasis market
"""

import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Union, Tuple
import logging

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


def calculate_market_metrics(price_df: pd.DataFrame, 
                            price_col: str = 'price_usd',
                            volume_col: Optional[str] = 'volume_24h',
                            market_cap_col: Optional[str] = 'market_cap',
                            date_col: str = 'timestamp',
                            window_sizes: List[int] = [7, 14, 30]) -> pd.DataFrame:
    """
    Hitung metrik pasar seperti volatilitas, rasio volume, dll.
    
    Args:
        price_df: DataFrame dengan data harga time series
        price_col: Nama kolom harga
        volume_col: Nama kolom volume perdagangan
        market_cap_col: Nama kolom kapitalisasi pasar
        date_col: Nama kolom tanggal
        window_sizes: Ukuran window untuk perhitungan metrik
        
    Returns:
        pd.DataFrame: DataFrame dengan metrik market tambahan
    """
    logger.info("Calculating market metrics")
    
    # Pastikan DataFrame disalin untuk menghindari perubahan pada original
    result_df = price_df.copy()
    
    # Pastikan date column adalah tipe datetime
    result_df[date_col] = pd.to_datetime(result_df[date_col])
    
    # Urutkan berdasarkan tanggal
    result_df = result_df.sort_values(date_col)
    
    # Hitung persentase perubahan harga (returns)
    result_df['daily_return'] = result_df[price_col].pct_change()
    
    # Hitung metrik untuk berbagai ukuran window
    for window in window_sizes:
        # 1. Volatilitas (standar deviasi returns)
        result_df[f'volatility_{window}d'] = result_df['daily_return'].rolling(window).std() * np.sqrt(window)
        
        # 2. Average True Range (ATR) - ukuran volatilitas
        if 'high' in result_df.columns and 'low' in result_df.columns:
            tr1 = (result_df['high'] - result_df['low']).abs()
            tr2 = (result_df['high'] - result_df[price_col].shift(1)).abs()
            tr3 = (result_df['low'] - result_df[price_col].shift(1)).abs()
            result_df['true_range'] = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
            result_df[f'atr_{window}d'] = result_df['true_range'].rolling(window).mean()
        
        # 3. Moving Averages
        result_df[f'ma_{window}d'] = result_df[price_col].rolling(window).mean()
        
        # 4. Relative Strength Index (RSI)
        delta = result_df[price_col].diff()
        gain = delta.where(delta > 0, 0)
        loss = -delta.where(delta < 0, 0)
        avg_gain = gain.rolling(window=window).mean()
        avg_loss = loss.rolling(window=window).mean()
        rs = avg_gain / avg_loss.where(avg_loss != 0, 0.001)  # Hindari division by zero
        result_df[f'rsi_{window}d'] = 100 - (100 / (1 + rs))
        
        # 5. Rate of Change (ROC)
        result_df[f'roc_{window}d'] = (result_df[price_col] / 
                                      result_df[price_col].shift(window) - 1) * 100
        
        # 6. Bollinger Bands
        ma = result_df[price_col].rolling(window).mean()
        std = result_df[price_col].rolling(window).std()
        result_df[f'bollinger_upper_{window}d'] = ma + 2 * std
        result_df[f'bollinger_lower_{window}d'] = ma - 2 * std
        result_df[f'bollinger_pct_{window}d'] = (result_df[price_col] - result_df[f'bollinger_lower_{window}d']) / (
            result_df[f'bollinger_upper_{window}d'] - result_df[f'bollinger_lower_{window}d'])
    
    # 7. Volume Metrics (jika tersedia)
    if volume_col in result_df.columns:
        # Volume Moving Average
        for window in window_sizes:
            result_df[f'volume_ma_{window}d'] = result_df[volume_col].rolling(window).mean()
        
        # Relative Volume (OBV - On-Balance Volume)
        result_df['obv_direction'] = np.where(result_df[price_col] > result_df[price_col].shift(1), 1,
                                            np.where(result_df[price_col] < result_df[price_col].shift(1), -1, 0))
        result_df['obv_volume'] = result_df[volume_col] * result_df['obv_direction']
        result_df['obv'] = result_df['obv_volume'].cumsum()
        
        # Volume Ratio (perbandingan dengan rata-rata volume)
        result_df['volume_ratio_14d'] = result_df[volume_col] / result_df[volume_col].rolling(14).mean()
    
    # 8. Market Cap Metrics (jika tersedia)
    if market_cap_col in result_df.columns and volume_col in result_df.columns:
        # Volume to Market Cap ratio (liquidity indicator)
        result_df['volume_to_mcap'] = result_df[volume_col] / result_df[market_cap_col]
    
    # 9. MACD (Moving Average Convergence Divergence)
    ema12 = result_df[price_col].ewm(span=12, adjust=False).mean()
    ema26 = result_df[price_col].ewm(span=26, adjust=False).mean()
    result_df['macd'] = ema12 - ema26
    result_df['macd_signal'] = result_df['macd'].ewm(span=9, adjust=False).mean()
    result_df['macd_histogram'] = result_df['macd'] - result_df['macd_signal']
    
    # 10. Average Directional Index (ADX) - trend strength
    if 'high' in result_df.columns and 'low' in result_df.columns:
        result_df['dm_plus'] = np.where(
            (result_df['high'] - result_df['high'].shift(1)) > (result_df['low'].shift(1) - result_df['low']),
            np.maximum(result_df['high'] - result_df['high'].shift(1), 0),
            0
        )
        result_df['dm_minus'] = np.where(
            (result_df['low'].shift(1) - result_df['low']) > (result_df['high'] - result_df['high'].shift(1)),
            np.maximum(result_df['low'].shift(1) - result_df['low'], 0),
            0
        )
        
        atr14 = result_df['true_range'].rolling(14).mean()
        result_df['di_plus'] = 100 * (result_df['dm_plus'].rolling(14).mean() / atr14)
        result_df['di_minus'] = 100 * (result_df['dm_minus'].rolling(14).mean() / atr14)
        
        result_df['dx'] = 100 * (abs(result_df['di_plus'] - result_df['di_minus']) /
                               (result_df['di_plus'] + result_df['di_minus']).replace(0, 0.001))
        result_df['adx'] = result_df['dx'].rolling(14).mean()
    
    # Hitung composite sentiment score berdasarkan indikator-indikator
    result_df['market_sentiment'] = calculate_market_sentiment(result_df)
    
    return result_df


def calculate_market_sentiment(data: pd.DataFrame) -> pd.Series:
    """
    Hitung skor sentimen pasar berdasarkan indikator teknikal
    
    Args:
        data: DataFrame dengan indikator pasar
        
    Returns:
        pd.Series: Skor sentimen pasar (0-100)
    """
    # Inisialisasi skor dasar
    sentiment_score = pd.Series(index=data.index, data=50)  # Netral di 50
    
    indicators = []
    weights = []
    
    # 1. RSI
    if 'rsi_14d' in data.columns:
        # RSI > 70 (overbought/bearish), RSI < 30 (oversold/bullish)
        rsi = data['rsi_14d'].fillna(50)
        rsi_score = 100 - rsi  # Invert karena RSI tinggi = bearish
        indicators.append(rsi_score)
        weights.append(0.15)
    
    # 2. MACD
    if all(col in data.columns for col in ['macd', 'macd_signal']):
        # MACD > Signal (bullish), MACD < Signal (bearish)
        macd_diff = data['macd'] - data['macd_signal']
        macd_max = abs(macd_diff).max()
        if macd_max > 0:
            macd_score = 50 + 50 * (macd_diff / macd_max)
            indicators.append(macd_score)
            weights.append(0.15)
    
    # 3. Bollinger %B
    if 'bollinger_pct_20d' in data.columns:
        # %B near 1 (overbought/bearish), %B near 0 (oversold/bullish)
        bb_score = 100 - data['bollinger_pct_20d'].fillna(0.5) * 100
        indicators.append(bb_score)
        weights.append(0.10)
    
    # 4. Price vs MA
    if 'ma_20d' in data.columns and 'price_usd' in data.columns:
        # Price > MA (bullish), Price < MA (bearish)
        price_vs_ma = (data['price_usd'] / data['ma_20d']).fillna(1)
        price_ma_score = 50 + 50 * (price_vs_ma - 1)
        # Clip to reasonable range
        price_ma_score = price_ma_score.clip(0, 100)
        indicators.append(price_ma_score)
        weights.append(0.20)
    
    # 5. ROC (Rate of Change)
    if 'roc_14d' in data.columns:
        # ROC > 0 (bullish), ROC < 0 (bearish)
        roc_max = abs(data['roc_14d']).max()
        if roc_max > 0:
            roc_score = 50 + 50 * (data['roc_14d'] / roc_max)
            roc_score = roc_score.clip(0, 100)
            indicators.append(roc_score)
            weights.append(0.15)
    
    # 6. ADX (trend strength)
    if 'adx' in data.columns:
        # ADX > 25 (strong trend), < 20 (weak trend)
        # Ini bukan indikator arah, jadi tidak mempengaruhi sentiment
        # Tapi bisa dijadikan faktor pengali untuk indikator lain
        adx_factor = (data['adx'] / 25).clip(0, 2)
        # Terapkan sebagai faktor amplifikasi
        if len(indicators) > 0:
            for i in range(len(indicators)):
                # Amplify deviation from neutral (50)
                indicators[i] = 50 + (indicators[i] - 50) * adx_factor
    
    # 7. Daily return
    if 'daily_return' in data.columns:
        return_score = 50 + data['daily_return'] * 1000  # Skala ke 0-100
        return_score = return_score.clip(0, 100)
        indicators.append(return_score)
        weights.append(0.25)
    
    # Kombinasikan indikator dengan weights
    if indicators:
        # Normalize weights
        weights = [w/sum(weights) for w in weights]
        
        # Weighted average
        for i, indicator in enumerate(indicators):
            sentiment_score = sentiment_score * 0 + indicators[i] * weights[i]
            
    return sentiment_score


def detect_market_events(price_df: pd.DataFrame,
                        price_col: str = 'price_usd',
                        volume_col: Optional[str] = 'volume_24h',
                        date_col: str = 'timestamp',
                        window_size: int = 14,
                        threshold_std: float = 2.0) -> pd.DataFrame:
    """
    Deteksi market events seperti pump, dump, volatilitas tinggi, dll.
    
    Args:
        price_df: DataFrame dengan data harga
        price_col: Nama kolom harga
        volume_col: Nama kolom volume
        date_col: Nama kolom tanggal
        window_size: Ukuran window untuk perhitungan
        threshold_std: Threshold standar deviasi untuk deteksi event
        
    Returns:
        pd.DataFrame: DataFrame dengan informasi event
    """
    logger.info("Detecting market events")
    
    # Pastikan DataFrame disalin
    df = price_df.copy()
    
    # Ensure date column is datetime
    df[date_col] = pd.to_datetime(df[date_col])
    
    # Sort by date
    df = df.sort_values(date_col)
    
    # Calculate returns
    df['return'] = df[price_col].pct_change()
    
    # Calculate volatility
    df['volatility'] = df['return'].rolling(window=window_size).std()
    
    # Calculate moving average and standard deviation
    df['price_ma'] = df[price_col].rolling(window=window_size).mean()
    df['price_std'] = df[price_col].rolling(window=window_size).std()
    
    # Calculate upper and lower bounds
    df['upper_bound'] = df['price_ma'] + (threshold_std * df['price_std'])
    df['lower_bound'] = df['price_ma'] - (threshold_std * df['price_std'])
    
    # Detect pump events (price significantly above moving average)
    df['pump'] = (df[price_col] > df['upper_bound']).astype(int)
    
    # Detect dump events (price significantly below moving average)
    df['dump'] = (df[price_col] < df['lower_bound']).astype(int)
    
    # Detect high volatility events
    vol_mean = df['volatility'].mean()
    vol_std = df['volatility'].std()
    df['high_volatility'] = (df['volatility'] > vol_mean + (threshold_std * vol_std)).astype(int)
    
    # Detect volume spikes if volume data is available
    if volume_col in df.columns:
        df['volume_ma'] = df[volume_col].rolling(window=window_size).mean()
        df['volume_std'] = df[volume_col].rolling(window=window_size).std()
        df['volume_spike'] = (df[volume_col] > df['volume_ma'] + threshold_std * df['volume_std']).astype(int)
    
    # Detect continuous price movements
    df['price_up'] = (df[price_col] > df[price_col].shift(1)).astype(int)
    df['consecutive_up'] = df['price_up'].rolling(window=3).sum()
    df['price_streak_up'] = (df['consecutive_up'] >= 3).astype(int)
    
    df['price_down'] = (df[price_col] < df[price_col].shift(1)).astype(int)
    df['consecutive_down'] = df['price_down'].rolling(window=3).sum()
    df['price_streak_down'] = (df['consecutive_down'] >= 3).astype(int)
    
    # Create an event type column
    event_map = {
        0: 'normal',
        1: 'pump',
        2: 'dump',
        3: 'high_volatility',
        4: 'volume_spike',
        5: 'streak_up',
        6: 'streak_down'
    }
    
    # Assign event type with priority
    df['event_type'] = 0  # Default to normal
    
    # Apply events with priority (higher numbers take precedence)
    priority_events = [
        ('pump', 1),
        ('dump', 2),
        ('high_volatility', 3),
        ('volume_spike', 4),
        ('price_streak_up', 5),
        ('price_streak_down', 6)
    ]
    
    for event, value in priority_events:
        if event in df.columns:
            df.loc[df[event] == 1, 'event_type'] = value
    
    # Map numeric event types to strings
    df['event_name'] = df['event_type'].map(event_map)
    
    return df