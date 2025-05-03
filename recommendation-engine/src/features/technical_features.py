"""
Modul untuk ekstraksi dan perhitungan fitur teknikal menggunakan TA-Lib
dengan dukungan periode indikator dinamis
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
    
    def __init__(self, price_data: Optional[pd.DataFrame] = None, indicator_periods: Optional[Dict[str, Any]] = None):
        """
        Inisialisasi analyzer dengan periode indikator yang fleksibel
        
        Args:
            price_data: DataFrame data harga (opsional)
            indicator_periods: Dictionary periode indikator kustom:
                - rsi_period: Periode RSI (default 14)
                - macd_fast: Periode MACD fast EMA (default 12)
                - macd_slow: Periode MACD slow EMA (default 26)
                - macd_signal: Periode MACD signal line (default 9)
                - bb_period: Periode Bollinger Bands (default 20)
                - stoch_k: Periode Stochastic %K (default 14)
                - stoch_d: Periode Stochastic %D (default 3)
                - ma_short: Periode MA jangka pendek (default 20)
                - ma_medium: Periode MA jangka menengah (default 50)
                - ma_long: Periode MA jangka panjang (default 200)
        """
        self.data = price_data
        
        # Set default periods jika tidak disediakan
        self.periods = {
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
            'adx_period': 14,
            'cci_period': 20,
            'roc_period': 10,
            'willr_period': 14
        }
        
        # Update dengan periode kustom jika disediakan
        if indicator_periods is not None:
            for key, value in indicator_periods.items():
                if key in self.periods:
                    self.periods[key] = value
        
        # Gunakan pandas-ta sebagai fallback jika TA-Lib tidak tersedia
        self.use_talib = TALIB_AVAILABLE
        self.use_pandas_ta = PANDAS_TA_AVAILABLE
        
        # Log informasi periode yang digunakan
        logger.info(f"TechnicalAnalyzer initialized with periods: RSI={self.periods['rsi_period']}, "
                   f"MACD={self.periods['macd_fast']}/{self.periods['macd_slow']}/{self.periods['macd_signal']}, "
                   f"BB={self.periods['bb_period']}, Stoch={self.periods['stoch_k']}/{self.periods['stoch_d']}, "
                   f"MA={self.periods['ma_short']}/{self.periods['ma_medium']}/{self.periods['ma_long']}")
    
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
                          close_col: str = 'current_price',
                          volume_col: Optional[str] = 'total_volume') -> pd.DataFrame:
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
        ma_periods = [self.periods['ma_short'], self.periods['ma_medium'], self.periods['ma_long']]
        
        if self.use_talib:
            for period in ma_periods:
                df[f'sma_{period}'] = talib.SMA(df[close_col], timeperiod=period)
                df[f'ema_{period}'] = talib.EMA(df[close_col], timeperiod=period)
            
            # MACD
            df['macd'], df['macd_signal'], df['macd_hist'] = talib.MACD(
                df[close_col], 
                fastperiod=self.periods['macd_fast'], 
                slowperiod=self.periods['macd_slow'], 
                signalperiod=self.periods['macd_signal']
            )
            
            # Parabolic SAR
            df['sar'] = talib.SAR(df['high'], df['low'], acceleration=0.02, maximum=0.2)
            
            # ADX - Average Directional Index
            df['adx'] = talib.ADX(df['high'], df['low'], df[close_col], timeperiod=self.periods['adx_period'])
            
        elif self.use_pandas_ta:
            # Use pandas-ta as fallback
            for period in ma_periods:
                df[f'sma_{period}'] = df.ta.sma(close=close_col, length=period)
                df[f'ema_{period}'] = df.ta.ema(close=close_col, length=period)
            
            macd = df.ta.macd(
                close=close_col, 
                fast=self.periods['macd_fast'], 
                slow=self.periods['macd_slow'], 
                signal=self.periods['macd_signal']
            )
            df = pd.concat([df, macd], axis=1)
            
            # Parabolic SAR
            sar = df.ta.psar(high='high', low='low')
            df = pd.concat([df, sar], axis=1)
            
            # ADX
            adx = df.ta.adx(high='high', low='low', close=close_col, length=self.periods['adx_period'])
            df = pd.concat([df, adx], axis=1)
        else:
            # Calculate using numpy/pandas
            for period in ma_periods:
                df[f'sma_{period}'] = df[close_col].rolling(window=period).mean()
                df[f'ema_{period}'] = df[close_col].ewm(span=period, adjust=False).mean()
            
            # Calculate MACD manually
            df['ema_fast'] = df[close_col].ewm(span=self.periods['macd_fast'], adjust=False).mean()
            df['ema_slow'] = df[close_col].ewm(span=self.periods['macd_slow'], adjust=False).mean()
            df['macd'] = df['ema_fast'] - df['ema_slow']
            df['macd_signal'] = df['macd'].ewm(span=self.periods['macd_signal'], adjust=False).mean()
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
            df['rsi'] = talib.RSI(df[close_col], timeperiod=self.periods['rsi_period'])
            
            # CCI - Commodity Channel Index
            df['cci'] = talib.CCI(df['high'], df['low'], df[close_col], timeperiod=self.periods['cci_period'])
            
            # Stochastic
            df['slowk'], df['slowd'] = talib.STOCH(
                df['high'], df['low'], df[close_col],
                fastk_period=self.periods['stoch_k'], 
                slowk_period=self.periods['stoch_d'], 
                slowk_matype=0, 
                slowd_period=self.periods['stoch_d'], 
                slowd_matype=0
            )
            
            # ROC - Rate of Change
            df['roc'] = talib.ROC(df[close_col], timeperiod=self.periods['roc_period'])
            
            # Williams %R
            df['willr'] = talib.WILLR(df['high'], df['low'], df[close_col], timeperiod=self.periods['willr_period'])
            
        elif self.use_pandas_ta:
            # Use pandas-ta as fallback
            df['rsi'] = df.ta.rsi(close=close_col, length=self.periods['rsi_period'])
            
            cci = df.ta.cci(high='high', low='low', close=close_col, length=self.periods['cci_period'])
            df = pd.concat([df, cci], axis=1)
            
            stoch = df.ta.stoch(
                high='high', 
                low='low', 
                close=close_col, 
                k=self.periods['stoch_k'], 
                d=self.periods['stoch_d'], 
                smooth_k=self.periods['stoch_d']
            )
            df = pd.concat([df, stoch], axis=1)
            
            df['roc'] = df.ta.roc(close=close_col, length=self.periods['roc_period'])
            
            df['willr'] = df.ta.willr(high='high', low='low', close=close_col, length=self.periods['willr_period'])
        else:
            # Calculate manually using pandas/numpy
            # RSI - Relative Strength Index
            delta = df[close_col].diff()
            gain = delta.where(delta > 0, 0)
            loss = -delta.where(delta < 0, 0)
            avg_gain = gain.rolling(window=self.periods['rsi_period']).mean()
            avg_loss = loss.rolling(window=self.periods['rsi_period']).mean()
            rs = avg_gain / avg_loss.where(avg_loss != 0, 0.001)  # Prevent division by zero
            df['rsi'] = 100 - (100 / (1 + rs))
            
            # ROC - Rate of Change
            df['roc'] = (df[close_col] / df[close_col].shift(self.periods['roc_period']) - 1) * 100
    
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
                df[close_col], 
                timeperiod=self.periods['bb_period'], 
                nbdevup=2, 
                nbdevdn=2, 
                matype=0
            )
            
            # ATR - Average True Range
            df['atr'] = talib.ATR(df[high_col], df[low_col], df[close_col], timeperiod=self.periods['atr_period'])
            
            # Standard Deviation
            df['stddev'] = talib.STDDEV(df[close_col], timeperiod=self.periods['bb_period'], nbdev=1)
            
        elif self.use_pandas_ta:
            # Use pandas-ta as fallback
            bbands = df.ta.bbands(close=close_col, length=self.periods['bb_period'], std=2)
            df = pd.concat([df, bbands], axis=1)
            
            atr = df.ta.atr(high='high', low='low', close=close_col, length=self.periods['atr_period'])
            df = pd.concat([df, atr], axis=1)
            
            df['stddev'] = df.ta.stdev(close=close_col, length=self.periods['bb_period'])
            
        else:
            # Calculate manually using pandas/numpy
            # Bollinger Bands
            df['bb_middle'] = df[close_col].rolling(window=self.periods['bb_period']).mean()
            df['stddev'] = df[close_col].rolling(window=self.periods['bb_period']).std()
            df['bb_upper'] = df['bb_middle'] + 2 * df['stddev']
            df['bb_lower'] = df['bb_middle'] - 2 * df['stddev']
            
            # ATR - Average True Range
            tr1 = abs(df[high_col] - df[low_col])
            tr2 = abs(df[high_col] - df[close_col].shift(1))
            tr3 = abs(df[low_col] - df[close_col].shift(1))
            df['tr'] = pd.DataFrame({'tr1': tr1, 'tr2': tr2, 'tr3': tr3}).max(axis=1)
            df['atr'] = df['tr'].rolling(window=self.periods['atr_period']).mean()
    
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
            df['mfi'] = talib.MFI(df['high'], df['low'], df[close_col], df[volume_col], timeperiod=self.periods['rsi_period'])
            
            # Chaikin A/D Line
            df['ad'] = talib.AD(df['high'], df['low'], df[close_col], df[volume_col])
            
            # Chaikin A/D Oscillator
            df['adosc'] = talib.ADOSC(df['high'], df['low'], df[close_col], df[volume_col], fastperiod=3, slowperiod=10)
            
        elif self.use_pandas_ta:
            # Use pandas-ta as fallback
            df['obv'] = df.ta.obv(close=close_col, volume=volume_col)
            
            mfi = df.ta.mfi(high='high', low='low', close=close_col, volume=volume_col, length=self.periods['rsi_period'])
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
            df['vpr'] = df[volume_col] / df[volume_col].rolling(window=self.periods['ma_short']).mean()
    
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
            df['linearreg'] = talib.LINEARREG(df[close_col], timeperiod=self.periods['rsi_period'])
            df['linearreg_slope'] = talib.LINEARREG_SLOPE(df[close_col], timeperiod=self.periods['rsi_period'])
            df['linearreg_angle'] = talib.LINEARREG_ANGLE(df[close_col], timeperiod=self.periods['rsi_period'])
            df['linearreg_intercept'] = talib.LINEARREG_INTERCEPT(df[close_col], timeperiod=self.periods['rsi_period'])
            
            # Variance
            df['var'] = talib.VAR(df[close_col], timeperiod=self.periods['rsi_period'], nbdev=1)
            
        elif self.use_pandas_ta:
            # These indicators are not directly available in pandas-ta
            # Calculate some statistics manually
            df['linearreg_slope'] = df.ta.slope(close=close_col, length=self.periods['rsi_period'])
    
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
        if 'rsi' in df.columns:
            df['rsi_smoothed'] = df['rsi'].rolling(window=3).mean()
        
        # RSI Divergence (hanya indikator sederhana)
        if 'rsi' in df.columns:
            df['rsi_slope'] = df['rsi'].diff(5)
            df['price_slope'] = df[close_col].diff(5)
            
            # Bullish divergence: price makes higher high but RSI makes lower high
            df['bullish_divergence'] = ((df['price_slope'] < 0) & (df['rsi_slope'] > 0)).astype(int)
            
            # Bearish divergence: price makes lower low but RSI makes higher low
            df['bearish_divergence'] = ((df['price_slope'] > 0) & (df['rsi_slope'] < 0)).astype(int)
        
        # Price Rate of Change Indicator dengan berbagai periode
        roc_periods = [
            5,  # 1 week (trading days)
            21,  # 1 month (trading days)
            63,  # 3 months (trading days)
            126,  # 6 months (trading days)
            252  # 1 year (trading days)
        ]
        for period in roc_periods:
            df[f'price_roc_{period}'] = df[close_col].pct_change(periods=period) * 100
        
        # Volatility Ratio
        if 'atr' in df.columns:
            df['volatility_ratio'] = df['atr'] / df[close_col] * 100
        
        # Ehlers Fisher Transform of RSI
        if 'rsi' in df.columns:
            # Normalize RSI between -1 and 1
            df['rsi_norm'] = df['rsi'] / 50 - 1
            # Apply Fisher Transform
            df['fisher_rsi'] = 0.5 * np.log((1 + df['rsi_norm']) / (1 - df['rsi_norm'].clip(-0.999, 0.999)))
        
        # Composite Momentum Indicator
        if all(col in df.columns for col in ['rsi', 'roc']):
            df['composite_momentum'] = (df['rsi'] + df['roc'] * 2) / 3
        
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
        if 'rsi' in df.columns:
            # RSI < 30 (bullish/oversold), RSI > 70 (bearish/overbought)
            df['bull_score'] += np.where(df['rsi'] < 30, 20, 
                                       np.where(df['rsi'] < 40, 10, 0))
            df['bear_score'] += np.where(df['rsi'] > 70, 20, 
                                        np.where(df['rsi'] > 60, 10, 0))
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
        ma_short = f'sma_{self.periods["ma_short"]}'
        ma_medium = f'sma_{self.periods["ma_medium"]}'
        ma_long = f'sma_{self.periods["ma_long"]}'
        
        if ma_medium in df.columns and ma_long in df.columns:
            # Golden Cross / Death Cross (jangka panjang)
            df['golden_cross'] = ((df[ma_medium] > df[ma_long]) & 
                                 (df[ma_medium].shift(1) <= df[ma_long].shift(1)))
            df['death_cross'] = ((df[ma_medium] < df[ma_long]) & 
                                (df[ma_medium].shift(1) >= df[ma_long].shift(1)))
            
            df['bull_score'] += np.where(df['golden_cross'], 20, 
                                       np.where(df[ma_medium] > df[ma_long], 5, 0))
            df['bear_score'] += np.where(df['death_cross'], 20, 
                                        np.where(df[ma_medium] < df[ma_long], 5, 0))
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
    
    
def generate_trading_signals(price_data: pd.DataFrame, indicator_periods: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    """
    Generate trading signals dari data harga
    
    Args:
        price_data: DataFrame dengan data harga
        indicator_periods: Dictionary periode indikator kustom
        
    Returns:
        dict: Dictionary dengan trading signals dan confidence level
    """
    analyzer = TechnicalAnalyzer(price_data, indicator_periods)
    
    # Add all technical indicators
    df_with_indicators = analyzer.add_all_indicators()
    
    # Get latest values for signals
    latest = df_with_indicators.iloc[-1]
    
    signal = latest.get('signal', 'hold')
    confidence = latest.get('confidence', 0.5)
    
    # Generate explanations based on key indicators
    explanation = []
    
    if 'rsi' in df_with_indicators.columns:
        rsi = latest['rsi']
        rsi_period = analyzer.periods['rsi_period']
        if rsi > 70:
            explanation.append(f"RSI overbought pada level {rsi:.1f} (periode {rsi_period})")
        elif rsi < 30:
            explanation.append(f"RSI oversold pada level {rsi:.1f} (periode {rsi_period})")
            
    if all(col in df_with_indicators.columns for col in ['macd', 'macd_signal']):
        macd_fast = analyzer.periods['macd_fast']
        macd_slow = analyzer.periods['macd_slow']
        macd_signal_period = analyzer.periods['macd_signal']
        
        if latest.get('macd_cross_up', False):
            explanation.append(f"MACD memotong ke atas signal line (bullish) - ({macd_fast}/{macd_slow}/{macd_signal_period})")
        elif latest.get('macd_cross_down', False):
            explanation.append(f"MACD memotong ke bawah signal line (bearish) - ({macd_fast}/{macd_slow}/{macd_signal_period})")
            
    if all(col in df_with_indicators.columns for col in ['bb_upper', 'bb_lower', 'current_price']):
        price = latest['current_price']
        bb_period = analyzer.periods['bb_period']
        
        if price > latest['bb_upper']:
            explanation.append(f"Harga di atas upper Bollinger Band (overbought) - (periode {bb_period})")
        elif price < latest['bb_lower']:
            explanation.append(f"Harga di bawah lower Bollinger Band (oversold) - (periode {bb_period})")
            
    if 'golden_cross' in df_with_indicators.columns and latest.get('golden_cross', False):
        explanation.append(f"Golden Cross: MA {analyzer.periods['ma_medium']} hari memotong di atas MA {analyzer.periods['ma_long']} hari")
    if 'death_cross' in df_with_indicators.columns and latest.get('death_cross', False):
        explanation.append(f"Death Cross: MA {analyzer.periods['ma_medium']} hari memotong di bawah MA {analyzer.periods['ma_long']} hari")
    
    # Target price calculation (simple implementation)
    target_price = None
    current_price = latest.get('current_price', None)
    atr = latest.get('atr', None)
    
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
        'evidence': explanation,
        'indicators': {
            'rsi': latest.get('rsi'),
            'macd': latest.get('macd'),
            'signal': latest.get('macd_signal'),
            'bull_score': latest.get('bull_score'),
            'bear_score': latest.get('bear_score')
        },
        'indicator_periods': analyzer.periods
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
        'Close': 'current_price',
        'Volume': 'total_volume'
    })
    
    # Test dengan periode standar
    print("\n=== Indikator dengan periode standar ===")
    analyzer_standard = TechnicalAnalyzer(data)
    processed_data_standard = analyzer_standard.add_all_indicators()
    
    # Test dengan periode jangka pendek
    print("\n=== Indikator dengan periode jangka pendek ===")
    short_term_periods = {
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
    analyzer_short = TechnicalAnalyzer(data, short_term_periods)
    processed_data_short = analyzer_short.add_all_indicators()
    
    # Test dengan periode jangka panjang
    print("\n=== Indikator dengan periode jangka panjang ===")
    long_term_periods = {
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
    analyzer_long = TechnicalAnalyzer(data, long_term_periods)
    processed_data_long = analyzer_long.add_all_indicators()
    
    # Generate and compare signals
    signals_standard = generate_trading_signals(data)
    signals_short = generate_trading_signals(data, short_term_periods)
    signals_long = generate_trading_signals(data, long_term_periods)
    
    # Print results
    print("\n=== Perbandingan Sinyal Trading ===")
    print(f"Standard: {signals_standard['action'].upper()} (Confidence: {signals_standard['confidence']:.2f})")
    print(f"Short-term: {signals_short['action'].upper()} (Confidence: {signals_short['confidence']:.2f})")
    print(f"Long-term: {signals_long['action'].upper()} (Confidence: {signals_long['confidence']:.2f})")
    
    print("\n=== Indikator untuk Standard ===")
    for evidence in signals_standard['evidence']:
        print(f"- {evidence}")
        
    print("\n=== Indikator untuk Short-term ===")
    for evidence in signals_short['evidence']:
        print(f"- {evidence}")
        
    print("\n=== Indikator untuk Long-term ===")
    for evidence in signals_long['evidence']:
        print(f"- {evidence}")