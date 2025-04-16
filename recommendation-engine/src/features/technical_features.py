"""
Modul untuk ekstraksi dan perhitungan fitur teknikal menggunakan TA-Lib
"""

import pandas as pd
import numpy as np
import logging
from typing import Dict, List, Optional, Tuple, Any, Union
import os
import warnings

# Coba import TA-Lib (jika diinstal)
try:
    import talib
    TALIB_AVAILABLE = True
except ImportError:
    TALIB_AVAILABLE = False
    warnings.warn("TA-Lib not available, using pandas-ta fallback.")
    try:
        import pandas_ta as ta
        PANDAS_TA_AVAILABLE = True
    except ImportError:
        PANDAS_TA_AVAILABLE = False
        warnings.warn("pandas-ta not available, only custom indicators will be used.")

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class TechnicalAnalyzer:
    """
    Class untuk ekstraksi fitur teknikal dari data harga
    """
    
    def __init__(self, price_data: Optional[pd.DataFrame] = None):
        """
        Inisialisasi analyzer
        
        Args:
            price_data: DataFrame data harga (opsional)
        """
        self.data = price_data
        
        # Gunakan pandas-ta sebagai fallback jika TA-Lib tidak tersedia
        self.use_talib = TALIB_AVAILABLE
        self.use_pandas_ta = PANDAS_TA_AVAILABLE
    
    def set_data(self, price_data: pd.DataFrame) -> None:
        """
        Set data harga untuk analisis
        
        Args:
            price_data: DataFrame data harga
        """
        self.data = price_data
    
    def add_all_indicators(self, 
                          open_col: str = 'open',
                          high_col: str = 'high',
                          low_col: str = 'low',
                          close_col: str = 'price_usd',
                          volume_col: Optional[str] = 'volume_24h') -> pd.DataFrame:
        """
        Tambahkan semua indikator teknikal ke data
        
        Args:
            open_col: Nama kolom open price
            high_col: Nama kolom high price
            low_col: Nama kolom low price
            close_col: Nama kolom close price
            volume_col: Nama kolom volume
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator teknikal
        """
        if self.data is None:
            raise ValueError("Price data not set. Use set_data() first.")
        
        # Create a copy to avoid modifying the original
        df = self.data.copy()
        
        # Check if required columns exist, use close for missing columns
        if high_col not in df.columns:
            df[high_col] = df[close_col]
            logger.warning(f"High column {high_col} not found, using close price instead.")
        
        if low_col not in df.columns:
            df[low_col] = df[close_col]
            logger.warning(f"Low column {low_col} not found, using close price instead.")
        
        if open_col not in df.columns:
            df[open_col] = df[close_col]
            logger.warning(f"Open column {open_col} not found, using close price instead.")
        
        if volume_col is not None and volume_col not in df.columns:
            volume_col = None
            logger.warning(f"Volume column {volume_col} not found, skipping volume indicators.")
        
        try:
            # Indikator trend
            self._add_trend_indicators(df, close_col)
            
            # Indikator momentum
            self._add_momentum_indicators(df, close_col)
            
            # Indikator volatilitas
            self._add_volatility_indicators(df, high_col, low_col, close_col)
            
            # Indikator volume (jika tersedia)
            if volume_col:
                self._add_volume_indicators(df, close_col, volume_col)
            
            # Indikator cycle
            self._add_cycle_indicators(df, close_col)
            
            # Indikator pattern recognition
            self._add_pattern_indicators(df, open_col, high_col, low_col, close_col)
            
            # Indikator statistik
            self._add_statistic_indicators(df, close_col)
            
            # Indikator custom
            df = self._add_custom_indicators(df, close_col)
            
            return df
            
        except Exception as e:
            logger.error(f"Error adding technical indicators: {e}")
            return df
        
    def _add_trend_indicators(self, df: pd.DataFrame, close_col: str) -> None:
        """
        Tambahkan indikator trend
        
        Args:
            df: DataFrame data harga
            close_col: Nama kolom close price
        """
        # Moving Average berbagai periode
        periods = [5, 10, 20, 50, 100, 200]
        
        if self.use_talib:
            for period in periods:
                df[f'sma_{period}'] = talib.SMA(df[close_col], timeperiod=period)
                df[f'ema_{period}'] = talib.EMA(df[close_col], timeperiod=period)
            
            # MACD
            df['macd'], df['macd_signal'], df['macd_hist'] = talib.MACD(
                df[close_col], fastperiod=12, slowperiod=26, signalperiod=9
            )
            
            # Parabolic SAR
            df['sar'] = talib.SAR(df['high'], df['low'], acceleration=0.02, maximum=0.2)
            
            # ADX - Average Directional Index
            df['adx'] = talib.ADX(df['high'], df['low'], df[close_col], timeperiod=14)
            
        elif self.use_pandas_ta:
            # Use pandas-ta as fallback
            for period in periods:
                df[f'sma_{period}'] = df.ta.sma(close=close_col, length=period)
                df[f'ema_{period}'] = df.ta.ema(close=close_col, length=period)
            
            macd = df.ta.macd(close=close_col, fast=12, slow=26, signal=9)
            df = pd.concat([df, macd], axis=1)
            
            # Parabolic SAR
            sar = df.ta.psar(high='high', low='low')
            df = pd.concat([df, sar], axis=1)
            
            # ADX
            adx = df.ta.adx(high='high', low='low', close=close_col, length=14)
            df = pd.concat([df, adx], axis=1)
        else:
            # Calculate using numpy/pandas
            for period in periods:
                df[f'sma_{period}'] = df[close_col].rolling(window=period).mean()
                df[f'ema_{period}'] = df[close_col].ewm(span=period, adjust=False).mean()
            
            # Calculate MACD manually
            df['ema_12'] = df[close_col].ewm(span=12, adjust=False).mean()
            df['ema_26'] = df[close_col].ewm(span=26, adjust=False).mean()
            df['macd'] = df['ema_12'] - df['ema_26']
            df['macd_signal'] = df['macd'].ewm(span=9, adjust=False).mean()
            df['macd_hist'] = df['macd'] - df['macd_signal']
    
    def _add_momentum_indicators(self, df: pd.DataFrame, close_col: str) -> None:
        """
        Tambahkan indikator momentum
        
        Args:
            df: DataFrame data harga
            close_col: Nama kolom close price
        """
        if self.use_talib:
            # RSI - Relative Strength Index
            df['rsi_14'] = talib.RSI(df[close_col], timeperiod=14)
            
            # CCI - Commodity Channel Index
            df['cci_14'] = talib.CCI(df['high'], df['low'], df[close_col], timeperiod=14)
            
            # Stochastic
            df['slowk'], df['slowd'] = talib.STOCH(
                df['high'], df['low'], df[close_col],
                fastk_period=5, slowk_period=3, slowk_matype=0, slowd_period=3, slowd_matype=0
            )
            
            # ROC - Rate of Change
            df['roc_10'] = talib.ROC(df[close_col], timeperiod=10)
            
            # Williams %R
            df['willr_14'] = talib.WILLR(df['high'], df['low'], df[close_col], timeperiod=14)
            
        elif self.use_pandas_ta:
            # Use pandas-ta as fallback
            df['rsi_14'] = df.ta.rsi(close=close_col, length=14)
            
            cci = df.ta.cci(high='high', low='low', close=close_col, length=14)
            df = pd.concat([df, cci], axis=1)
            
            stoch = df.ta.stoch(high='high', low='low', close=close_col, k=5, d=3, smooth_k=3)
            df = pd.concat([df, stoch], axis=1)
            
            df['roc_10'] = df.ta.roc(close=close_col, length=10)
            
            df['willr_14'] = df.ta.willr(high='high', low='low', close=close_col, length=14)
        else:
            # Calculate manually using pandas/numpy
            # RSI - Relative Strength Index
            delta = df[close_col].diff()
            gain = delta.where(delta > 0, 0)
            loss = -delta.where(delta < 0, 0)
            avg_gain = gain.rolling(window=14).mean()
            avg_loss = loss.rolling(window=14).mean()
            rs = avg_gain / avg_loss.where(avg_loss != 0, 0.001)  # Prevent division by zero
            df['rsi_14'] = 100 - (100 / (1 + rs))
            
            # ROC - Rate of Change
            df['roc_10'] = (df[close_col] / df[close_col].shift(10) - 1) * 100
    
    def _add_volatility_indicators(self, df: pd.DataFrame, high_col: str, low_col: str, close_col: str) -> None:
        """
        Tambahkan indikator volatilitas
        
        Args:
            df: DataFrame data harga
            high_col: Nama kolom high price
            low_col: Nama kolom low price
            close_col: Nama kolom close price
        """
        if self.use_talib:
            # Bollinger Bands
            df['bb_upper'], df['bb_middle'], df['bb_lower'] = talib.BBANDS(
                df[close_col], timeperiod=20, nbdevup=2, nbdevdn=2, matype=0
            )
            
            # ATR - Average True Range
            df['atr_14'] = talib.ATR(df[high_col], df[low_col], df[close_col], timeperiod=14)
            
            # Standard Deviation
            df['stddev_20'] = talib.STDDEV(df[close_col], timeperiod=20, nbdev=1)
            
        elif self.use_pandas_ta:
            # Use pandas-ta as fallback
            bbands = df.ta.bbands(close=close_col, length=20, std=2)
            df = pd.concat([df, bbands], axis=1)
            
            atr = df.ta.atr(high='high', low='low', close=close_col, length=14)
            df = pd.concat([df, atr], axis=1)
            
            df['stddev_20'] = df.ta.stdev(close=close_col, length=20)
            
        else:
            # Calculate manually using pandas/numpy
            # Bollinger Bands
            df['bb_middle'] = df[close_col].rolling(window=20).mean()
            df['stddev_20'] = df[close_col].rolling(window=20).std()
            df['bb_upper'] = df['bb_middle'] + 2 * df['stddev_20']
            df['bb_lower'] = df['bb_middle'] - 2 * df['stddev_20']
            
            # ATR - Average True Range
            tr1 = abs(df[high_col] - df[low_col])
            tr2 = abs(df[high_col] - df[close_col].shift(1))
            tr3 = abs(df[low_col] - df[close_col].shift(1))
            df['tr'] = pd.DataFrame({'tr1': tr1, 'tr2': tr2, 'tr3': tr3}).max(axis=1)
            df['atr_14'] = df['tr'].rolling(window=14).mean()
    
    def _add_volume_indicators(self, df: pd.DataFrame, close_col: str, volume_col: str) -> None:
        """
        Tambahkan indikator volume
        
        Args:
            df: DataFrame data harga
            close_col: Nama kolom close price
            volume_col: Nama kolom volume
        """
        if self.use_talib:
            # On Balance Volume
            df['obv'] = talib.OBV(df[close_col], df[volume_col])
            
            # Money Flow Index
            df['mfi_14'] = talib.MFI(df['high'], df['low'], df[close_col], df[volume_col], timeperiod=14)
            
            # Chaikin A/D Line
            df['ad'] = talib.AD(df['high'], df['low'], df[close_col], df[volume_col])
            
            # Chaikin A/D Oscillator
            df['adosc'] = talib.ADOSC(df['high'], df['low'], df[close_col], df[volume_col], fastperiod=3, slowperiod=10)
            
        elif self.use_pandas_ta:
            # Use pandas-ta as fallback
            df['obv'] = df.ta.obv(close=close_col, volume=volume_col)
            
            mfi = df.ta.mfi(high='high', low='low', close=close_col, volume=volume_col, length=14)
            df = pd.concat([df, mfi], axis=1)
            
            ad = df.ta.ad(high='high', low='low', close=close_col, volume=volume_col)
            df = pd.concat([df, ad], axis=1)
            
            adosc = df.ta.adosc(high='high', low='low', close=close_col, volume=volume_col, fast=3, slow=10)
            df = pd.concat([df, adosc], axis=1)
            
        else:
            # Calculate OBV manually
            df['obv_direction'] = np.where(df[close_col] > df[close_col].shift(1), 1,
                                        np.where(df[close_col] < df[close_col].shift(1), -1, 0))
            df['obv_volume'] = df[volume_col] * df['obv_direction']
            df['obv'] = df['obv_volume'].cumsum()
            
            # Simple Volume-Price Ratio
            df['vpr'] = df[volume_col] / df[volume_col].rolling(window=20).mean()
    
    def _add_cycle_indicators(self, df: pd.DataFrame, close_col: str) -> None:
        """
        Tambahkan indikator cycle
        
        Args:
            df: DataFrame data harga
            close_col: Nama kolom close price
        """
        if self.use_talib:
            # Hilbert Transform - Dominant Cycle Period
            df['ht_dcperiod'] = talib.HT_DCPERIOD(df[close_col])
            
            # Hilbert Transform - Dominant Cycle Phase
            df['ht_dcphase'] = talib.HT_DCPHASE(df[close_col])
            
            # Hilbert Transform - Phasor Components
            df['ht_inphase'], df['ht_quadrature'] = talib.HT_PHASOR(df[close_col])
            
            # Hilbert Transform - SineWave
            df['ht_sine'], df['ht_leadsine'] = talib.HT_SINE(df[close_col])
            
        elif self.use_pandas_ta:
            # Hilbert Transform cycle indicators tidak tersedia di pandas-ta
            pass
    
    def _add_pattern_indicators(self, df: pd.DataFrame, open_col: str, high_col: str, 
                               low_col: str, close_col: str) -> None:
        """
        Tambahkan indikator pattern recognition
        
        Args:
            df: DataFrame data harga
            open_col: Nama kolom open price
            high_col: Nama kolom high price
            low_col: Nama kolom low price
            close_col: Nama kolom close price
        """
        if self.use_talib:
            # Candlestick Patterns
            df['doji'] = talib.CDLDOJI(df[open_col], df[high_col], df[low_col], df[close_col])
            df['engulfing'] = talib.CDLENGULFING(df[open_col], df[high_col], df[low_col], df[close_col])
            df['hammer'] = talib.CDLHAMMER(df[open_col], df[high_col], df[low_col], df[close_col])
            df['hanging_man'] = talib.CDLHANGINGMAN(df[open_col], df[high_col], df[low_col], df[close_col])
            df['shooting_star'] = talib.CDLSHOOTINGSTAR(df[open_col], df[high_col], df[low_col], df[close_col])
            df['evening_star'] = talib.CDLEVENINGSTAR(df[open_col], df[high_col], df[low_col], df[close_col])
            df['morning_star'] = talib.CDLMORNINGSTAR(df[open_col], df[high_col], df[low_col], df[close_col])
            
        elif self.use_pandas_ta:
            # Candlestick pattern not available in pandas-ta
            pass
    
    def _add_statistic_indicators(self, df: pd.DataFrame, close_col: str) -> None:
        """
        Tambahkan indikator statistik
        
        Args:
            df: DataFrame data harga
            close_col: Nama kolom close price
        """
        if self.use_talib:
            # Linear Regression
            df['linearreg'] = talib.LINEARREG(df[close_col], timeperiod=14)
            df['linearreg_slope'] = talib.LINEARREG_SLOPE(df[close_col], timeperiod=14)
            df['linearreg_angle'] = talib.LINEARREG_ANGLE(df[close_col], timeperiod=14)
            df['linearreg_intercept'] = talib.LINEARREG_INTERCEPT(df[close_col], timeperiod=14)
            
            # Variance
            df['var_14'] = talib.VAR(df[close_col], timeperiod=14, nbdev=1)
            
        elif self.use_pandas_ta:
            # These indicators are not directly available in pandas-ta
            # Calculate some statistics manually
            df['linearreg_slope'] = df.ta.slope(close=close_col, length=14)
    
    def _add_custom_indicators(self, df: pd.DataFrame, close_col: str) -> pd.DataFrame:
        """
        Tambahkan indikator custom
        
        Args:
            df: DataFrame data harga
            close_col: Nama kolom close price
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator custom
        """
        # Moving Average Convergence Divergence Percentage (MACDP)
        if 'macd' in df.columns and 'macd_signal' in df.columns:
            df['macdp'] = (df['macd'] - df['macd_signal']) / df[close_col] * 100
        
        # Smoothed RSI
        if 'rsi_14' in df.columns:
            df['rsi_smoothed'] = df['rsi_14'].rolling(window=3).mean()
        
        # RSI Divergence (hanya indikator sederhana)
        if 'rsi_14' in df.columns:
            df['rsi_slope'] = df['rsi_14'].diff(5)
            df['price_slope'] = df[close_col].diff(5)
            
            # Bullish divergence (price: down, RSI: up)
            df['bullish_divergence'] = ((df['price_slope'] < 0) & (df['rsi_slope'] > 0)).astype(int)
            
            # Bearish divergence (price: up, RSI: down)
            df['bearish_divergence'] = ((df['price_slope'] > 0) & (df['rsi_slope'] < 0)).astype(int)
        
        # Price Rate of Change Indicator dengan berbagai periode
        for period in [5, 21, 63, 126, 252]:  # Daily: 1 week, 1 month, 3 months, 6 months, 1 year
            df[f'price_roc_{period}'] = df[close_col].pct_change(periods=period) * 100
        
        # Volatility Ratio
        if 'atr_14' in df.columns:
            df['volatility_ratio'] = df['atr_14'] / df[close_col] * 100
        
        # Ehlers Fisher Transform of RSI
        if 'rsi_14' in df.columns:
            # Normalize RSI between -1 and 1
            df['rsi_norm'] = df['rsi_14'] / 50 - 1
            # Apply Fisher Transform
            df['fisher_rsi'] = 0.5 * np.log((1 + df['rsi_norm']) / (1 - df['rsi_norm'].clip(-0.999, 0.999)))
        
        # Composite Momentum Indicator
        if all(col in df.columns for col in ['rsi_14', 'roc_10']):
            df['composite_momentum'] = (df['rsi_14'] + df['roc_10'] * 2) / 3
        
        # Bull/Bear Power
        df['bull_power'] = df['high'] - df[close_col].rolling(window=13).mean()
        df['bear_power'] = df['low'] - df[close_col].rolling(window=13).mean()
        
        # Distance from All-Time High
        df['ath'] = df[close_col].cummax()
        df['ath_distance'] = (df[close_col] / df['ath'] - 1) * 100
        
        # Hitung composite indicator untuk trading signals
        df = self._calculate_trading_signal(df, close_col)
        
        return df
    
    def _calculate_trading_signal(self, df: pd.DataFrame, close_col: str) -> pd.DataFrame:
        """
        Hitung sinyal trading berdasarkan kombinasi indikator
        
        Args:
            df: DataFrame dengan indikator teknikal
            close_col: Nama kolom close price
            
        Returns:
            pd.DataFrame: DataFrame dengan sinyal trading
        """
        # Inisialisasi skor dan signals
        df['bull_score'] = 50  # Nilai netral
        df['bear_score'] = 50  # Nilai netral
        
        indicators_count = 0  # Penghitung indikator yang digunakan
        
        # 1. RSI
        if 'rsi_14' in df.columns:
            # RSI < 30 (bullish/oversold), RSI > 70 (bearish/overbought)
            df['bull_score'] += np.where(df['rsi_14'] < 30, 20, 
                                       np.where(df['rsi_14'] < 40, 10, 0))
            df['bear_score'] += np.where(df['rsi_14'] > 70, 20, 
                                        np.where(df['rsi_14'] > 60, 10, 0))
            indicators_count += 1
            
        # 2. MACD
        if all(col in df.columns for col in ['macd', 'macd_signal']):
            # MACD crosses above signal (bullish), below signal (bearish)
            df['macd_cross_up'] = ((df['macd'] > df['macd_signal']) & 
                                  (df['macd'].shift(1) <= df['macd_signal'].shift(1)))
            df['macd_cross_down'] = ((df['macd'] < df['macd_signal']) & 
                                    (df['macd'].shift(1) >= df['macd_signal'].shift(1)))
            
            df['bull_score'] += np.where(df['macd_cross_up'], 15, 
                                       np.where(df['macd'] > df['macd_signal'], 5, 0))
            df['bear_score'] += np.where(df['macd_cross_down'], 15, 
                                        np.where(df['macd'] < df['macd_signal'], 5, 0))
            indicators_count += 1
            
        # 3. Bollinger Bands
        if all(col in df.columns for col in ['bb_upper', 'bb_lower']):
            # Price < Lower BB (bullish/oversold), Price > Upper BB (bearish/overbought)
            df['bull_score'] += np.where(df[close_col] < df['bb_lower'], 15, 0)
            df['bear_score'] += np.where(df[close_col] > df['bb_upper'], 15, 0)
            indicators_count += 1
            
        # 4. Moving Averages
        if 'sma_50' in df.columns and 'sma_200' in df.columns:
            # Golden Cross / Death Cross (jangka panjang)
            df['golden_cross'] = ((df['sma_50'] > df['sma_200']) & 
                                 (df['sma_50'].shift(1) <= df['sma_200'].shift(1)))
            df['death_cross'] = ((df['sma_50'] < df['sma_200']) & 
                                (df['sma_50'].shift(1) >= df['sma_200'].shift(1)))
            
            df['bull_score'] += np.where(df['golden_cross'], 20, 
                                       np.where(df['sma_50'] > df['sma_200'], 5, 0))
            df['bear_score'] += np.where(df['death_cross'], 20, 
                                        np.where(df['sma_50'] < df['sma_200'], 5, 0))
            indicators_count += 1
            
        # 5. Volume-based signals (if available)
        if 'obv' in df.columns:
            # OBV trend tidak sesuai dengan price trend (divergence)
            df['obv_slope'] = df['obv'].diff(5)
            df['price_slope'] = df[close_col].diff(5)
            
            # Bullish divergence (price: down, OBV: up)
            df['obv_bull_divergence'] = ((df['price_slope'] < 0) & (df['obv_slope'] > 0))
            
            # Bearish divergence (price: up, OBV: down)
            df['obv_bear_divergence'] = ((df['price_slope'] > 0) & (df['obv_slope'] < 0))
            
            df['bull_score'] += np.where(df['obv_bull_divergence'], 10, 0)
            df['bear_score'] += np.where(df['obv_bear_divergence'], 10, 0)
            indicators_count += 1
            
        # Normalize scores based on number of indicators used
        if indicators_count > 0:
            # Adjust max potential score to 100
            max_score = 50 + 50 * indicators_count / 5  # 5 indikator = max score
            df['bull_score'] = 50 + (df['bull_score'] - 50) * 50 / (max_score - 50)
            df['bear_score'] = 50 + (df['bear_score'] - 50) * 50 / (max_score - 50)
            
            # Clip scores to 0-100 range
            df['bull_score'] = df['bull_score'].clip(0, 100)
            df['bear_score'] = df['bear_score'].clip(0, 100)
        
        # Generate overall signal
        df['signal_value'] = df['bull_score'] - df['bear_score'] + 50  # 0-100 scale with 50 as neutral
        
        # Map to signal text
        conditions = [
            (df['signal_value'] >= 75, 'strong_buy'),
            (df['signal_value'] >= 60, 'buy'),
            (df['signal_value'] <= 25, 'strong_sell'),
            (df['signal_value'] <= 40, 'sell'),
        ]
        default = 'hold'
        
        df['signal'] = default
        for condition, value in conditions:
            df.loc[condition, 'signal'] = value
            
        # Add confidence value (how strong is the signal)
        df['confidence'] = (abs(df['signal_value'] - 50) / 50)
        
        return df
    
    
def generate_trading_signals(price_data: pd.DataFrame, window_size: int = 14) -> Dict[str, Any]:
    """
    Generate trading signals dari data harga
    
    Args:
        price_data: DataFrame dengan data harga
        window_size: Ukuran window untuk perhitungan indikator
        
    Returns:
        dict: Dictionary dengan trading signals dan confidence level
    """
    analyzer = TechnicalAnalyzer(price_data)
    
    # Add all technical indicators
    df_with_indicators = analyzer.add_all_indicators()
    
    # Get latest values for signals
    latest = df_with_indicators.iloc[-1]
    
    signal = latest.get('signal', 'hold')
    confidence = latest.get('confidence', 0.5)
    
    # Generate explanations based on key indicators
    explanation = []
    
    if 'rsi_14' in df_with_indicators.columns:
        rsi = latest['rsi_14']
        if rsi > 70:
            explanation.append(f"RSI is overbought at {rsi:.1f}")
        elif rsi < 30:
            explanation.append(f"RSI is oversold at {rsi:.1f}")
            
    if all(col in df_with_indicators.columns for col in ['macd', 'macd_signal']):
        if latest.get('macd_cross_up', False):
            explanation.append("MACD crossed above signal line (bullish)")
        elif latest.get('macd_cross_down', False):
            explanation.append("MACD crossed below signal line (bearish)")
            
    if all(col in df_with_indicators.columns for col in ['bb_upper', 'bb_lower', 'price_usd']):
        price = latest['price_usd']
        if price > latest['bb_upper']:
            explanation.append("Price is above upper Bollinger Band (overbought)")
        elif price < latest['bb_lower']:
            explanation.append("Price is below lower Bollinger Band (oversold)")
            
    if 'golden_cross' in df_with_indicators.columns and latest.get('golden_cross', False):
        explanation.append("50-day MA crossed above 200-day MA (Golden Cross)")
    if 'death_cross' in df_with_indicators.columns and latest.get('death_cross', False):
        explanation.append("50-day MA crossed below 200-day MA (Death Cross)")
    
    # Target price calculation (simple implementation)
    target_price = None
    current_price = latest.get('price_usd', None)
    atr = latest.get('atr_14', None)
    
    if current_price is not None and atr is not None:
        if signal in ['buy', 'strong_buy']:
            # Target up: current price + 2*ATR for buy signals
            target_price = current_price * (1 + 2 * atr / current_price)
        elif signal in ['sell', 'strong_sell']:
            # Target down: current price - 2*ATR for sell signals
            target_price = current_price * (1 - 2 * atr / current_price)
    
    return {
        'action': signal,
        'confidence': confidence,
        'target_price': target_price,
        'reasons': explanation,
        'indicators': {
            'rsi': latest.get('rsi_14'),
            'macd': latest.get('macd'),
            'signal': latest.get('macd_signal'),
            'bull_score': latest.get('bull_score'),
            'bear_score': latest.get('bear_score')
        }
    }


if __name__ == "__main__":
    # Test mode
    import yfinance as yf
    
    # Download sample data
    ticker = "BTC-USD"
    data = yf.download(ticker, period="6mo")
    
    print(f"Downloaded {len(data)} rows of data for {ticker}")
    
    # Rename columns to match our expected format
    data = data.rename(columns={
        'Open': 'open',
        'High': 'high',
        'Low': 'low',
        'Close': 'price_usd',
        'Volume': 'volume_24h'
    })
    
    # Process with our technical analyzer
    analyzer = TechnicalAnalyzer(data)
    processed_data = analyzer.add_all_indicators()
    
    print(f"Added technical indicators. Data now has {len(processed_data.columns)} columns")
    
    # Generate trading signals
    signals = generate_trading_signals(data)
    
    print("\nTrading Signals:")
    print(f"Action: {signals['action'].upper()}")
    print(f"Confidence: {signals['confidence']:.2f}")
    if signals['target_price']:
        print(f"Target Price: ${signals['target_price']:.2f}")
    
    print("\nReasons:")
    for reason in signals['reasons']:
        print(f"- {reason}")
        
    print("\nKey Indicators:")
    for indicator, value in signals['indicators'].items():
        if value is not None:
            print(f"- {indicator}: {value:.2f}")