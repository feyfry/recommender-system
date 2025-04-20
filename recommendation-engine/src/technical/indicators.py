"""
Modul untuk menghitung indikator-indikator teknikal (VERSI YANG DIPERBARUI - DENGAN PERIODE DINAMIS)
"""

import os
import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple, Any, Union
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

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class TechnicalIndicators:
    """
    Class untuk menghitung indikator-indikator teknikal
    """
    
    def __init__(self, prices_df: pd.DataFrame, indicator_periods: Optional[Dict[str, Any]] = None):
        """
        Inisialisasi dengan DataFrame harga dan periode indikator kustom
        
        Args:
            prices_df: DataFrame dengan data harga (harus memiliki kolom 'close',
                      opsional 'high', 'low', 'volume')
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
                - atr_period: Periode ATR (default 14)
                - adx_period: Periode ADX (default 14)
                - cci_period: Periode CCI (default 20)
                - roc_period: Periode ROC (default 10)
                - willr_period: Periode Williams %R (default 14)
        """
        self.prices_df = prices_df
        
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
                    
        # Verify required columns
        if 'close' not in prices_df.columns:
            raise ValueError("DataFrame must have 'close' column")
            
        # Use appropriate column names or defaults
        self.close_col = 'close'
        self.high_col = 'high' if 'high' in prices_df.columns else self.close_col
        self.low_col = 'low' if 'low' in prices_df.columns else self.close_col
        self.volume_col = 'volume' if 'volume' in prices_df.columns else None
        
        # Log informasi periode yang digunakan
        logger.info(f"TechnicalIndicators initialized with periods: RSI={self.periods['rsi_period']}, "
                   f"MACD={self.periods['macd_fast']}/{self.periods['macd_slow']}/{self.periods['macd_signal']}, "
                   f"BB={self.periods['bb_period']}, Stoch={self.periods['stoch_k']}/{self.periods['stoch_d']}, "
                   f"MA={self.periods['ma_short']}/{self.periods['ma_medium']}/{self.periods['ma_long']}")
        
        # Check if we have enough data for calculations
        min_required_points = max(
            3 * self.periods['rsi_period'],
            self.periods['macd_slow'] + self.periods['macd_signal'] + 10,
            self.periods['bb_period'] + 10,
            self.periods['stoch_k'] + self.periods['stoch_d'] + 5,
            self.periods['ma_long'] + 10
        )
        
        if len(prices_df) < min_required_points:
            logger.warning(f"DataFrame has only {len(prices_df)} rows, which might not be sufficient for all indicators "
                         f"with selected periods. Minimum recommended: {min_required_points}")
    
    def add_indicators(self) -> pd.DataFrame:
        """
        Tambahkan indikator teknikal ke DataFrame
        
        Returns:
            pd.DataFrame: DataFrame dengan indikator teknikal
        """
        try:
            # Create a copy to avoid modifying the original
            df = self.prices_df.copy()
            
            # Convert all necessary columns to numeric first to prevent type errors
            for col in [self.close_col, self.high_col, self.low_col]:
                if col in df.columns:
                    df[col] = pd.to_numeric(df[col], errors='coerce')
                    
            if self.volume_col is not None and self.volume_col in df.columns:
                df[self.volume_col] = pd.to_numeric(df[self.volume_col], errors='coerce')
            
            # Add different types of indicators with error handling
            try:
                df = self.add_trend_indicators(df)
            except Exception as e:
                logger.error(f"Error adding trend indicators: {str(e)}")
                
            try:
                df = self.add_momentum_indicators(df)
            except Exception as e:
                logger.error(f"Error adding momentum indicators: {str(e)}")
                
            try:
                df = self.add_volatility_indicators(df)
            except Exception as e:
                logger.error(f"Error adding volatility indicators: {str(e)}")
            
            if self.volume_col is not None:
                try:
                    df = self.add_volume_indicators(df)
                except Exception as e:
                    logger.error(f"Error adding volume indicators: {str(e)}")
            
            # Add combined signal columns
            try:
                df = self.add_signal_indicators(df)
            except Exception as e:
                logger.error(f"Error adding signal indicators: {str(e)}")
            
            return df
            
        except Exception as e:
            import traceback
            logger.error(f"Error adding indicators: {str(e)}")
            logger.error(traceback.format_exc())
            # Return original DataFrame if there's an error
            return self.prices_df.copy()
    
    def add_trend_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator tren
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator tren
        """
        # Moving Averages
        ma_short = self.periods['ma_short']
        ma_medium = self.periods['ma_medium']
        ma_long = self.periods['ma_long']
        
        ma_periods = [ma_short, ma_medium, ma_long]
        
        for period in ma_periods:
            if len(df) >= period:
                df[f'sma_{period}'] = df[self.close_col].rolling(window=period).mean()
                df[f'ema_{period}'] = df[self.close_col].ewm(span=period, adjust=False).mean()
        
        # MACD
        macd_fast = self.periods['macd_fast']
        macd_slow = self.periods['macd_slow']
        macd_signal = self.periods['macd_signal']
        
        if len(df) >= macd_slow:
            if TALIB_AVAILABLE:
                df['macd'], df['macd_signal'], df['macd_hist'] = talib.MACD(
                    df[self.close_col].values, 
                    fastperiod=macd_fast, 
                    slowperiod=macd_slow, 
                    signalperiod=macd_signal
                )
            else:
                # Calculate MACD manually
                fast_ema = df[self.close_col].ewm(span=macd_fast, adjust=False).mean()
                slow_ema = df[self.close_col].ewm(span=macd_slow, adjust=False).mean()
                df['macd'] = fast_ema - slow_ema
                df['macd_signal'] = df['macd'].ewm(span=macd_signal, adjust=False).mean()
                df['macd_hist'] = df['macd'] - df['macd_signal']
        
        # ADX (Average Directional Index)
        adx_period = self.periods['adx_period']
        
        if len(df) >= adx_period:
            if TALIB_AVAILABLE:
                df['adx'] = talib.ADX(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=adx_period
                )
                df['plus_di'] = talib.PLUS_DI(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=adx_period
                )
                df['minus_di'] = talib.MINUS_DI(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=adx_period
                )
            else:
                # Use simplified implementation from signals.py
                # 1. True Range
                prev_close = df[self.close_col].shift(1)
                tr1 = df[self.high_col] - df[self.low_col]  # High - Low
                tr2 = (df[self.high_col] - prev_close).abs()  # |High - Previous Close|
                tr3 = (df[self.low_col] - prev_close).abs()  # |Low - Previous Close|
                
                true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
                atr14 = true_range.rolling(window=adx_period).mean()
                
                # 2. Directional Movement
                high_diff = df[self.high_col] - df[self.high_col].shift(1)
                low_diff = df[self.low_col].shift(1) - df[self.low_col]
                
                plus_dm = high_diff.where((high_diff > low_diff) & (high_diff > 0), 0)
                minus_dm = low_diff.where((low_diff > high_diff) & (low_diff > 0), 0)
                
                # 3. Directional Indicators
                df['plus_di'] = 100 * (plus_dm.rolling(window=adx_period).mean() / atr14)
                df['minus_di'] = 100 * (minus_dm.rolling(window=adx_period).mean() / atr14)
                
                # 4. Directional Index
                dx = 100 * ((df['plus_di'] - df['minus_di']).abs() / 
                           (df['plus_di'] + df['minus_di']).replace(0, np.finfo(float).eps))
                
                # 5. Average Directional Index
                df['adx'] = dx.rolling(window=adx_period).mean()
        
        # Parabolic SAR
        if len(df) >= 10:
            if TALIB_AVAILABLE:
                df['sar'] = talib.SAR(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    acceleration=0.02, 
                    maximum=0.2
                )
        
        # Add Moving Average Crossovers
        if f'sma_{ma_short}' in df.columns and f'sma_{ma_medium}' in df.columns:
            df['sma_short_medium_cross'] = np.where(
                df[f'sma_{ma_short}'] > df[f'sma_{ma_medium}'], 1, -1
            )
            # Detect crosses
            df['sma_short_medium_cross_up'] = np.where(
                (df[f'sma_{ma_short}'] > df[f'sma_{ma_medium}']) & (df[f'sma_{ma_short}'].shift(1) <= df[f'sma_{ma_medium}'].shift(1)), 
                1, 0
            )
            df['sma_short_medium_cross_down'] = np.where(
                (df[f'sma_{ma_short}'] < df[f'sma_{ma_medium}']) & (df[f'sma_{ma_short}'].shift(1) >= df[f'sma_{ma_medium}'].shift(1)), 
                1, 0
            )
        
        # Add Death Cross / Golden Cross
        if f'sma_{ma_medium}' in df.columns and f'sma_{ma_long}' in df.columns:
            df['sma_medium_long_cross'] = np.where(
                df[f'sma_{ma_medium}'] > df[f'sma_{ma_long}'], 1, -1
            )
            # Detect crosses
            df['golden_cross'] = np.where(
                (df[f'sma_{ma_medium}'] > df[f'sma_{ma_long}']) & (df[f'sma_{ma_medium}'].shift(1) <= df[f'sma_{ma_long}'].shift(1)), 
                1, 0
            )
            df['death_cross'] = np.where(
                (df[f'sma_{ma_medium}'] < df[f'sma_{ma_long}']) & (df[f'sma_{ma_medium}'].shift(1) >= df[f'sma_{ma_long}'].shift(1)), 
                1, 0
            )
        
        return df
    
    def add_momentum_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator momentum
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator momentum
        """
        # RSI
        rsi_period = self.periods['rsi_period']
        
        if len(df) >= rsi_period:
            if TALIB_AVAILABLE:
                df['rsi'] = talib.RSI(df[self.close_col].values, timeperiod=rsi_period)
            else:
                # Calculate RSI manually
                delta = df[self.close_col].diff()
                gain = delta.where(delta > 0, 0)
                loss = -delta.where(delta < 0, 0)
                
                avg_gain = gain.rolling(window=rsi_period).mean()
                avg_loss = loss.rolling(window=rsi_period).mean()
                
                rs = avg_gain / avg_loss.replace(0, np.finfo(float).eps)
                df['rsi'] = 100 - (100 / (1 + rs))
        
        # Stochastic Oscillator
        stoch_k = self.periods['stoch_k']
        stoch_d = self.periods['stoch_d']
        
        if len(df) >= stoch_k:
            if TALIB_AVAILABLE:
                df['stoch_k'], df['stoch_d'] = talib.STOCH(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    fastk_period=stoch_k, 
                    slowk_period=stoch_d, 
                    slowk_matype=0, 
                    slowd_period=stoch_d, 
                    slowd_matype=0
                )
            else:
                # Calculate Stochastic Oscillator manually
                low_k = df[self.low_col].rolling(window=stoch_k).min()
                high_k = df[self.high_col].rolling(window=stoch_k).max()
                
                # Fast %K
                df['stoch_k'] = 100 * ((df[self.close_col] - low_k) / 
                                      (high_k - low_k).replace(0, np.finfo(float).eps))
                
                # Slow %D (3-period SMA of %K)
                df['stoch_d'] = df['stoch_k'].rolling(window=stoch_d).mean()
        
        # CCI (Commodity Channel Index)
        cci_period = self.periods['cci_period']
        
        if len(df) >= cci_period:
            if TALIB_AVAILABLE:
                df['cci'] = talib.CCI(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=cci_period
                )
            else:
                # Calculate CCI manually
                typical_price = (df[self.high_col] + df[self.low_col] + df[self.close_col]) / 3
                mean_deviation = np.abs(typical_price - typical_price.rolling(window=cci_period).mean()).rolling(window=cci_period).mean()
                
                df['cci'] = (typical_price - typical_price.rolling(window=cci_period).mean()) / (0.015 * mean_deviation.replace(0, np.finfo(float).eps))
        
        # ROC (Rate of Change)
        roc_period = self.periods['roc_period']
        
        if len(df) >= roc_period:
            if TALIB_AVAILABLE:
                df['roc'] = talib.ROC(df[self.close_col].values, timeperiod=roc_period)
            else:
                # Calculate ROC manually
                df['roc'] = (df[self.close_col] / df[self.close_col].shift(roc_period) - 1) * 100
        
        # Williams %R
        willr_period = self.periods['willr_period']
        
        if len(df) >= willr_period:
            if TALIB_AVAILABLE:
                df['willr'] = talib.WILLR(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=willr_period
                )
            else:
                # Calculate Williams %R manually
                highest_high = df[self.high_col].rolling(window=willr_period).max()
                lowest_low = df[self.low_col].rolling(window=willr_period).min()
                
                df['willr'] = -100 * (highest_high - df[self.close_col]) / (highest_high - lowest_low).replace(0, np.finfo(float).eps)
        
        return df
    
    def add_volatility_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator volatilitas
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator volatilitas
        """
        # Bollinger Bands
        bb_period = self.periods['bb_period']
        
        if len(df) >= bb_period:
            if TALIB_AVAILABLE:
                df['bb_upper'], df['bb_middle'], df['bb_lower'] = talib.BBANDS(
                    df[self.close_col].values, 
                    timeperiod=bb_period, 
                    nbdevup=2, 
                    nbdevdn=2, 
                    matype=0
                )
            else:
                # Calculate Bollinger Bands manually
                df['bb_middle'] = df[self.close_col].rolling(window=bb_period).mean()
                df['bb_std'] = df[self.close_col].rolling(window=bb_period).std()
                
                df['bb_upper'] = df['bb_middle'] + (2 * df['bb_std'])
                df['bb_lower'] = df['bb_middle'] - (2 * df['bb_std'])
            
            # Calculate %B
            df['bb_pct_b'] = (df[self.close_col] - df['bb_lower']) / (df['bb_upper'] - df['bb_lower']).replace(0, np.finfo(float).eps)
        
        # ATR (Average True Range)
        atr_period = self.periods['atr_period']
        
        if len(df) >= atr_period:
            if TALIB_AVAILABLE:
                df['atr'] = talib.ATR(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=atr_period
                )
            else:
                # Calculate ATR manually
                prev_close = df[self.close_col].shift(1)
                tr1 = df[self.high_col] - df[self.low_col]  # High - Low
                tr2 = (df[self.high_col] - prev_close).abs()  # |High - Previous Close|
                tr3 = (df[self.low_col] - prev_close).abs()  # |Low - Previous Close|
                
                true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
                df['atr'] = true_range.rolling(window=atr_period).mean()
            
            # Calculate ATR ratio (ATR/Price) as a volatility measure
            df['atr_ratio'] = df['atr'] / df[self.close_col]
        
        # Historical Volatility
        if len(df) >= bb_period:  # Gunakan BB period untuk konsistensi
            # Calculate daily returns
            df['daily_returns'] = df[self.close_col].pct_change()
            
            # Calculate 20-day rolling standard deviation of returns
            df[f'historical_volatility_{bb_period}d'] = df['daily_returns'].rolling(window=bb_period).std() * np.sqrt(252)  # Annualized
        
        return df
    
    def add_volume_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator volume
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator volume
        """
        if self.volume_col is None:
            logger.warning("Volume column not available, skipping volume indicators")
            return df
        
        # OBV (On Balance Volume)
        if TALIB_AVAILABLE:
            df['obv'] = talib.OBV(
                df[self.close_col].values, 
                df[self.volume_col].values
            )
        else:
            # Calculate OBV manually
            df['price_change'] = df[self.close_col].diff()
            df['obv'] = np.where(
                df['price_change'] > 0, 
                df[self.volume_col], 
                np.where(
                    df['price_change'] < 0, 
                    -df[self.volume_col], 
                    0
                )
            ).cumsum()
        
        # Volume Moving Averages
        ma_short = self.periods['ma_short']
        ma_medium = self.periods['ma_medium']
        
        df[f'volume_sma_{ma_short}'] = df[self.volume_col].rolling(window=ma_short).mean()
        df[f'volume_sma_{ma_medium}'] = df[self.volume_col].rolling(window=ma_medium).mean()
        
        # Volume Ratio (current volume to average volume)
        df['volume_ratio'] = df[self.volume_col] / df[f'volume_sma_{ma_medium}']
        
        # Money Flow Index (MFI)
        mfi_period = self.periods['rsi_period']  # Gunakan RSI period untuk MFI
        
        if len(df) >= mfi_period:
            if TALIB_AVAILABLE:
                df['mfi'] = talib.MFI(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    df[self.volume_col].values, 
                    timeperiod=mfi_period
                )
            else:
                # Calculate MFI manually
                typical_price = (df[self.high_col] + df[self.low_col] + df[self.close_col]) / 3
                money_flow = typical_price * df[self.volume_col]
                
                positive_flow = money_flow.where(typical_price > typical_price.shift(1), 0)
                negative_flow = money_flow.where(typical_price < typical_price.shift(1), 0)
                
                positive_flow_sum = positive_flow.rolling(window=mfi_period).sum()
                negative_flow_sum = negative_flow.rolling(window=mfi_period).sum()
                
                money_ratio = positive_flow_sum / negative_flow_sum.replace(0, np.finfo(float).eps)
                df['mfi'] = 100 - (100 / (1 + money_ratio))
        
        # Chaikin A/D Oscillator
        if TALIB_AVAILABLE:
            df['adosc'] = talib.ADOSC(
                df[self.high_col].values, 
                df[self.low_col].values, 
                df[self.close_col].values, 
                df[self.volume_col].values, 
                fastperiod=3, 
                slowperiod=10
            )
        
        return df
    
    def add_signal_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator sinyal/keputusan
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator sinyal
        """
        # RSI Signals
        if 'rsi' in df.columns:
            df['rsi_signal'] = np.where(
                df['rsi'] < 30, 'buy',
                np.where(df['rsi'] > 70, 'sell', 'hold')
            )
        
        # MACD Signals
        if all(col in df.columns for col in ['macd', 'macd_signal']):
            df['macd_cross_up'] = np.where(
                (df['macd'] > df['macd_signal']) & (df['macd'].shift(1) <= df['macd_signal'].shift(1)),
                1, 0
            )
            df['macd_cross_down'] = np.where(
                (df['macd'] < df['macd_signal']) & (df['macd'].shift(1) >= df['macd_signal'].shift(1)),
                1, 0
            )
            df['macd_signal'] = np.where(
                df['macd_cross_up'] == 1, 'buy',
                np.where(df['macd_cross_down'] == 1, 'sell',
                         np.where(df['macd'] > df['macd_signal'], 'buy', 'sell'))
            )
        
        # Bollinger Bands Signals
        if all(col in df.columns for col in ['bb_upper', 'bb_lower']):
            df['bb_signal'] = np.where(
                df[self.close_col] < df['bb_lower'], 'buy',
                np.where(df[self.close_col] > df['bb_upper'], 'sell', 'hold')
            )
        
        # Moving Average Signals
        ma_short = self.periods['ma_short']
        if f'sma_{ma_short}' in df.columns:
            df['ma_signal'] = np.where(
                df[self.close_col] > df[f'sma_{ma_short}'], 'buy',
                np.where(df[self.close_col] < df[f'sma_{ma_short}'], 'sell', 'hold')
            )
        
        # Stochastic Signals
        if 'stoch_k' in df.columns and 'stoch_d' in df.columns:
            df['stoch_cross_up'] = np.where(
                (df['stoch_k'] > df['stoch_d']) & (df['stoch_k'].shift(1) <= df['stoch_d'].shift(1)),
                1, 0
            )
            df['stoch_cross_down'] = np.where(
                (df['stoch_k'] < df['stoch_d']) & (df['stoch_k'].shift(1) >= df['stoch_d'].shift(1)),
                1, 0
            )
            df['stoch_signal'] = np.where(
                (df['stoch_k'] < 20) & (df['stoch_cross_up'] == 1), 'strong_buy',
                np.where((df['stoch_k'] > 80) & (df['stoch_cross_down'] == 1), 'strong_sell',
                         np.where(df['stoch_k'] < 20, 'buy',
                                  np.where(df['stoch_k'] > 80, 'sell', 'hold')))
            )
        
        # Combined Signal
        signal_columns = [col for col in ['rsi_signal', 'macd_signal', 'bb_signal', 'ma_signal', 'stoch_signal'] 
                         if col in df.columns]
        
        if signal_columns:
            # Create a composite signal count
            df['buy_signals'] = df[signal_columns].apply(
                lambda row: sum(1 for signal in row if signal in ['buy', 'strong_buy']),
                axis=1
            )
            df['sell_signals'] = df[signal_columns].apply(
                lambda row: sum(1 for signal in row if signal in ['sell', 'strong_sell']),
                axis=1
            )
            
            # Create overall signal based on majority vote
            df['overall_signal'] = np.where(
                df['buy_signals'] > df['sell_signals'], 'buy',
                np.where(df['sell_signals'] > df['buy_signals'], 'sell', 'hold')
            )
            
            # Signal strength (0-100 scale where 50 is neutral)
            total_signals = len(signal_columns)
            df['buy_strength'] = 50 + (50 * (df['buy_signals'] - df['sell_signals']) / total_signals)
            
            # Clip to 0-100 range
            df['buy_strength'] = df['buy_strength'].clip(0, 100)
        
        return df
    
    def generate_alerts(self, lookback_period: int = 1) -> List[Dict[str, Any]]:
        """
        Generate alerts based on technical indicators
        
        Args:
            lookback_period: Number of periods to look back for alerts
            
        Returns:
            list: List of alert dictionaries
        """
        # First add indicators if not already added
        if 'overall_signal' not in self.prices_df.columns:
            df = self.add_indicators()
        else:
            df = self.prices_df
            
        # Get the last n rows
        recent_data = df.iloc[-lookback_period:]
        
        alerts = []
        
        # Check for various alert conditions
        for idx, row in recent_data.iterrows():
            date = idx if isinstance(idx, pd.Timestamp) else pd.Timestamp(idx)
            
            # RSI alerts
            if 'rsi' in row:
                if row['rsi'] < 30:
                    alerts.append({
                        'date': date,
                        'type': 'rsi_oversold',
                        'message': f"RSI oversold pada level {row['rsi']:.2f} (periode {self.periods['rsi_period']})",
                        'signal': 'buy',
                        'strength': min(1.0, (30 - row['rsi']) / 15)
                    })
                elif row['rsi'] > 70:
                    alerts.append({
                        'date': date,
                        'type': 'rsi_overbought',
                        'message': f"RSI overbought pada level {row['rsi']:.2f} (periode {self.periods['rsi_period']})",
                        'signal': 'sell',
                        'strength': min(1.0, (row['rsi'] - 70) / 15)
                    })
            
            # MACD crosses
            if 'macd_cross_up' in row and row['macd_cross_up'] == 1:
                alerts.append({
                    'date': date,
                    'type': 'macd_cross_up',
                    'message': f"MACD memotong ke atas signal line (bullish) - ({self.periods['macd_fast']}/{self.periods['macd_slow']}/{self.periods['macd_signal']})",
                    'signal': 'buy',
                    'strength': 0.8
                })
            elif 'macd_cross_down' in row and row['macd_cross_down'] == 1:
                alerts.append({
                    'date': date,
                    'type': 'macd_cross_down',
                    'message': f"MACD memotong ke bawah signal line (bearish) - ({self.periods['macd_fast']}/{self.periods['macd_slow']}/{self.periods['macd_signal']})",
                    'signal': 'sell',
                    'strength': 0.8
                })
            
            # Bollinger Band alerts
            if all(band in row for band in ['bb_upper', 'bb_lower']):
                if row[self.close_col] > row['bb_upper']:
                    alerts.append({
                        'date': date,
                        'type': 'bb_upper_breach',
                        'message': f"Harga menembus di atas upper Bollinger Band (potensi pembalikan/lanjutan) - periode {self.periods['bb_period']}",
                        'signal': 'sell',
                        'strength': 0.7
                    })
                elif row[self.close_col] < row['bb_lower']:
                    alerts.append({
                        'date': date,
                        'type': 'bb_lower_breach',
                        'message': f"Harga menembus di bawah lower Bollinger Band (potensi pembalikan/lanjutan) - periode {self.periods['bb_period']}",
                        'signal': 'buy',
                        'strength': 0.7
                    })
            
            # Moving Average crosses
            if 'golden_cross' in row and row['golden_cross'] == 1:
                alerts.append({
                    'date': date,
                    'type': 'golden_cross',
                    'message': f"Golden Cross: MA {self.periods['ma_medium']} hari memotong di atas MA {self.periods['ma_long']} hari (bullish)",
                    'signal': 'buy',
                    'strength': 0.9
                })
            elif 'death_cross' in row and row['death_cross'] == 1:
                alerts.append({
                    'date': date,
                    'type': 'death_cross',
                    'message': f"Death Cross: MA {self.periods['ma_medium']} hari memotong di bawah MA {self.periods['ma_long']} hari (bearish)",
                    'signal': 'sell',
                    'strength': 0.9
                })
            
            # Price crossing MA
            ma_short = self.periods['ma_short']
            sma_key = f'sma_{ma_short}'
            if sma_key in row:
                try:
                    prev_idx = df.index.get_loc(idx) - 1
                    if prev_idx >= 0:  # Ensure we're not at the first row
                        prev_close = df.iloc[prev_idx][self.close_col]
                        prev_ma = df.iloc[prev_idx][sma_key]
                        
                        price_cross_up = (row[self.close_col] > row[sma_key]) and (prev_close <= prev_ma)
                        price_cross_down = (row[self.close_col] < row[sma_key]) and (prev_close >= prev_ma)
                        
                        if price_cross_up:
                            alerts.append({
                                'date': date,
                                'type': 'price_cross_ma_up',
                                'message': f"Harga memotong ke atas MA {ma_short} hari (bullish)",
                                'signal': 'buy',
                                'strength': 0.7
                            })
                        elif price_cross_down:
                            alerts.append({
                                'date': date,
                                'type': 'price_cross_ma_down',
                                'message': f"Harga memotong ke bawah MA {ma_short} hari (bearish)",
                                'signal': 'sell',
                                'strength': 0.7
                            })
                except:
                    pass  # Skip if we can't determine the index
            
            # Strong trend (ADX > 25)
            if 'adx' in row and 'plus_di' in row and 'minus_di' in row and row['adx'] > 25:
                if row['plus_di'] > row['minus_di']:
                    alerts.append({
                        'date': date,
                        'type': 'strong_uptrend',
                        'message': f"Tren naik kuat terdeteksi (ADX: {row['adx']:.2f})",
                        'signal': 'buy',
                        'strength': min(1.0, row['adx'] / 50)
                    })
                else:
                    alerts.append({
                        'date': date,
                        'type': 'strong_downtrend',
                        'message': f"Tren turun kuat terdeteksi (ADX: {row['adx']:.2f})",
                        'signal': 'sell',
                        'strength': min(1.0, row['adx'] / 50)
                    })
        
        return alerts


if __name__ == "__main__":
    # Example usage
    # Create sample price data
    rng = np.random.default_rng(42)  # Create Generator instance with seed
    n = 200
    dates = pd.date_range(end=pd.Timestamp.now(), periods=n)
    
    # Generate synthetic price data using rng methods
    close = 100 + np.cumsum(rng.normal(0, 1, n))
    high = close + rng.uniform(0, 3, n)
    low = close - rng.uniform(0, 3, n)
    volume = rng.uniform(1000, 5000, n)
    
    # Create DataFrame
    df = pd.DataFrame({
        'close': close,
        'high': high,
        'low': low,
        'volume': volume
    }, index=dates)
    
    # Test dengan periode standar
    print("\n=== Indikator dengan periode standar ===")
    ti_standard = TechnicalIndicators(df)
    df_standard = ti_standard.add_indicators()
    print(f"Indikator standar berhasil ditambahkan: {len(df_standard.columns)} kolom")
    
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
    ti_short = TechnicalIndicators(df, short_term_periods)
    df_short = ti_short.add_indicators()
    print(f"Indikator jangka pendek berhasil ditambahkan: {len(df_short.columns)} kolom")
    
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
    ti_long = TechnicalIndicators(df, long_term_periods)
    df_long = ti_long.add_indicators()
    print(f"Indikator jangka panjang berhasil ditambahkan: {len(df_long.columns)} kolom")
    
    # Show some indicators
    print("\nContoh Indikator:")
    for period_type, df_indicators in [("Standard", df_standard), ("Short-term", df_short), ("Long-term", df_long)]:
        print(f"\n{period_type}:")
        last_row = df_indicators.iloc[-1]
        print(f"RSI: {last_row.get('rsi', 'N/A'):.2f}")
        print(f"MACD: {last_row.get('macd', 'N/A'):.2f}")
        print(f"MACD Signal: {last_row.get('macd_signal', 'N/A') if isinstance(last_row.get('macd_signal'), str) else float(last_row.get('macd_signal', 0)):.2f}")
        print(f"Bollinger %B: {last_row.get('bb_pct_b', 'N/A'):.2f}")
        print(f"Overall Signal: {last_row.get('overall_signal', 'N/A')}")
    
    # Generate alerts
    alerts = ti_standard.generate_alerts(lookback_period=5)
    
    print("\nAlert Terdeteksi:")
    for alert in alerts:
        print(f"{alert['date']}: {alert['message']} ({alert['signal'].upper()} - Kekuatan: {alert['strength']:.2f})")