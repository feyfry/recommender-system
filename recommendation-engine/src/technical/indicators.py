"""
Modul untuk menghitung indikator-indikator teknikal
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
    
    def __init__(self, prices_df: pd.DataFrame):
        """
        Inisialisasi dengan DataFrame harga
        
        Args:
            prices_df: DataFrame dengan data harga (harus memiliki kolom 'close',
                      opsional 'high', 'low', 'volume')
        """
        self.prices_df = prices_df
        
        # Verify required columns
        if 'close' not in prices_df.columns:
            raise ValueError("DataFrame must have 'close' column")
            
        # Use appropriate column names or defaults
        self.close_col = 'close'
        self.high_col = 'high' if 'high' in prices_df.columns else self.close_col
        self.low_col = 'low' if 'low' in prices_df.columns else self.close_col
        self.volume_col = 'volume' if 'volume' in prices_df.columns else None
        
        # Check if we have enough data for calculations
        if len(prices_df) < 30:
            logger.warning(f"DataFrame has only {len(prices_df)} rows, which might not be sufficient for all indicators")
    
    def add_indicators(self) -> pd.DataFrame:
        """
        Tambahkan indikator teknikal ke DataFrame
        
        Returns:
            pd.DataFrame: DataFrame dengan indikator teknikal
        """
        # Create a copy to avoid modifying the original
        df = self.prices_df.copy()
        
        # Add different types of indicators
        df = self.add_trend_indicators(df)
        df = self.add_momentum_indicators(df)
        df = self.add_volatility_indicators(df)
        
        if self.volume_col is not None:
            df = self.add_volume_indicators(df)
        
        # Add combined signal columns
        df = self.add_signal_indicators(df)
        
        return df
    
    def add_trend_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator tren
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator tren
        """
        # Moving Averages
        ma_periods = [5, 10, 20, 50, 100, 200]
        
        for period in ma_periods:
            if len(df) >= period:
                df[f'sma_{period}'] = df[self.close_col].rolling(window=period).mean()
                df[f'ema_{period}'] = df[self.close_col].ewm(span=period, adjust=False).mean()
        
        # MACD
        if len(df) >= 26:
            if TALIB_AVAILABLE:
                df['macd'], df['macd_signal'], df['macd_hist'] = talib.MACD(
                    df[self.close_col].values, 
                    fastperiod=12, 
                    slowperiod=26, 
                    signalperiod=9
                )
            else:
                # Calculate MACD manually
                fast_ema = df[self.close_col].ewm(span=12, adjust=False).mean()
                slow_ema = df[self.close_col].ewm(span=26, adjust=False).mean()
                df['macd'] = fast_ema - slow_ema
                df['macd_signal'] = df['macd'].ewm(span=9, adjust=False).mean()
                df['macd_hist'] = df['macd'] - df['macd_signal']
        
        # ADX (Average Directional Index)
        if len(df) >= 14:
            if TALIB_AVAILABLE:
                df['adx'] = talib.ADX(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=14
                )
                df['plus_di'] = talib.PLUS_DI(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=14
                )
                df['minus_di'] = talib.MINUS_DI(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=14
                )
            else:
                # Use simplified implementation from signals.py
                # 1. True Range
                prev_close = df[self.close_col].shift(1)
                tr1 = df[self.high_col] - df[self.low_col]  # High - Low
                tr2 = (df[self.high_col] - prev_close).abs()  # |High - Previous Close|
                tr3 = (df[self.low_col] - prev_close).abs()  # |Low - Previous Close|
                
                true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
                atr14 = true_range.rolling(window=14).mean()
                
                # 2. Directional Movement
                high_diff = df[self.high_col] - df[self.high_col].shift(1)
                low_diff = df[self.low_col].shift(1) - df[self.low_col]
                
                plus_dm = high_diff.where((high_diff > low_diff) & (high_diff > 0), 0)
                minus_dm = low_diff.where((low_diff > high_diff) & (low_diff > 0), 0)
                
                # 3. Directional Indicators
                df['plus_di'] = 100 * (plus_dm.rolling(window=14).mean() / atr14)
                df['minus_di'] = 100 * (minus_dm.rolling(window=14).mean() / atr14)
                
                # 4. Directional Index
                dx = 100 * ((df['plus_di'] - df['minus_di']).abs() / 
                           (df['plus_di'] + df['minus_di']).replace(0, np.finfo(float).eps))
                
                # 5. Average Directional Index
                df['adx'] = dx.rolling(window=14).mean()
        
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
        if 'sma_20' in df.columns and 'sma_50' in df.columns:
            df['sma_20_50_cross'] = np.where(
                df['sma_20'] > df['sma_50'], 1, -1
            )
            # Detect crosses
            df['sma_20_50_cross_up'] = np.where(
                (df['sma_20'] > df['sma_50']) & (df['sma_20'].shift(1) <= df['sma_50'].shift(1)), 
                1, 0
            )
            df['sma_20_50_cross_down'] = np.where(
                (df['sma_20'] < df['sma_50']) & (df['sma_20'].shift(1) >= df['sma_50'].shift(1)), 
                1, 0
            )
        
        # Add Death Cross / Golden Cross
        if 'sma_50' in df.columns and 'sma_200' in df.columns:
            df['sma_50_200_cross'] = np.where(
                df['sma_50'] > df['sma_200'], 1, -1
            )
            # Detect crosses
            df['golden_cross'] = np.where(
                (df['sma_50'] > df['sma_200']) & (df['sma_50'].shift(1) <= df['sma_200'].shift(1)), 
                1, 0
            )
            df['death_cross'] = np.where(
                (df['sma_50'] < df['sma_200']) & (df['sma_50'].shift(1) >= df['sma_200'].shift(1)), 
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
        if len(df) >= 14:
            if TALIB_AVAILABLE:
                df['rsi'] = talib.RSI(df[self.close_col].values, timeperiod=14)
            else:
                # Calculate RSI manually
                delta = df[self.close_col].diff()
                gain = delta.where(delta > 0, 0)
                loss = -delta.where(delta < 0, 0)
                
                avg_gain = gain.rolling(window=14).mean()
                avg_loss = loss.rolling(window=14).mean()
                
                rs = avg_gain / avg_loss.replace(0, np.finfo(float).eps)
                df['rsi'] = 100 - (100 / (1 + rs))
        
        # Stochastic Oscillator
        if len(df) >= 14:
            if TALIB_AVAILABLE:
                df['stoch_k'], df['stoch_d'] = talib.STOCH(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    fastk_period=14, 
                    slowk_period=3, 
                    slowk_matype=0, 
                    slowd_period=3, 
                    slowd_matype=0
                )
            else:
                # Calculate Stochastic Oscillator manually
                low_14 = df[self.low_col].rolling(window=14).min()
                high_14 = df[self.high_col].rolling(window=14).max()
                
                # Fast %K
                df['stoch_k'] = 100 * ((df[self.close_col] - low_14) / 
                                      (high_14 - low_14).replace(0, np.finfo(float).eps))
                
                # Slow %D (3-period SMA of %K)
                df['stoch_d'] = df['stoch_k'].rolling(window=3).mean()
        
        # CCI (Commodity Channel Index)
        if len(df) >= 20:
            if TALIB_AVAILABLE:
                df['cci'] = talib.CCI(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=20
                )
            else:
                # Calculate CCI manually
                typical_price = (df[self.high_col] + df[self.low_col] + df[self.close_col]) / 3
                mean_deviation = np.abs(typical_price - typical_price.rolling(window=20).mean()).rolling(window=20).mean()
                
                df['cci'] = (typical_price - typical_price.rolling(window=20).mean()) / (0.015 * mean_deviation.replace(0, np.finfo(float).eps))
        
        # ROC (Rate of Change)
        if len(df) >= 10:
            if TALIB_AVAILABLE:
                df['roc'] = talib.ROC(df[self.close_col].values, timeperiod=10)
            else:
                # Calculate ROC manually
                df['roc'] = (df[self.close_col] / df[self.close_col].shift(10) - 1) * 100
        
        # Williams %R
        if len(df) >= 14:
            if TALIB_AVAILABLE:
                df['willr'] = talib.WILLR(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=14
                )
            else:
                # Calculate Williams %R manually
                highest_high = df[self.high_col].rolling(window=14).max()
                lowest_low = df[self.low_col].rolling(window=14).min()
                
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
        if len(df) >= 20:
            if TALIB_AVAILABLE:
                df['bb_upper'], df['bb_middle'], df['bb_lower'] = talib.BBANDS(
                    df[self.close_col].values, 
                    timeperiod=20, 
                    nbdevup=2, 
                    nbdevdn=2, 
                    matype=0
                )
            else:
                # Calculate Bollinger Bands manually
                df['bb_middle'] = df[self.close_col].rolling(window=20).mean()
                df['bb_std'] = df[self.close_col].rolling(window=20).std()
                
                df['bb_upper'] = df['bb_middle'] + (2 * df['bb_std'])
                df['bb_lower'] = df['bb_middle'] - (2 * df['bb_std'])
            
            # Calculate %B
            df['bb_pct_b'] = (df[self.close_col] - df['bb_lower']) / (df['bb_upper'] - df['bb_lower']).replace(0, np.finfo(float).eps)
        
        # ATR (Average True Range)
        if len(df) >= 14:
            if TALIB_AVAILABLE:
                df['atr'] = talib.ATR(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    timeperiod=14
                )
            else:
                # Calculate ATR manually
                prev_close = df[self.close_col].shift(1)
                tr1 = df[self.high_col] - df[self.low_col]  # High - Low
                tr2 = (df[self.high_col] - prev_close).abs()  # |High - Previous Close|
                tr3 = (df[self.low_col] - prev_close).abs()  # |Low - Previous Close|
                
                true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
                df['atr'] = true_range.rolling(window=14).mean()
            
            # Calculate ATR ratio (ATR/Price) as a volatility measure
            df['atr_ratio'] = df['atr'] / df[self.close_col]
        
        # Historical Volatility
        if len(df) >= 20:
            # Calculate daily returns
            df['daily_returns'] = df[self.close_col].pct_change()
            
            # Calculate 20-day rolling standard deviation of returns
            df['historical_volatility'] = df['daily_returns'].rolling(window=20).std() * np.sqrt(252)  # Annualized
        
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
        df['volume_sma_5'] = df[self.volume_col].rolling(window=5).mean()
        df['volume_sma_20'] = df[self.volume_col].rolling(window=20).mean()
        
        # Volume Ratio (current volume to average volume)
        df['volume_ratio'] = df[self.volume_col] / df['volume_sma_20']
        
        # Money Flow Index (MFI)
        if len(df) >= 14:
            if TALIB_AVAILABLE:
                df['mfi'] = talib.MFI(
                    df[self.high_col].values, 
                    df[self.low_col].values, 
                    df[self.close_col].values, 
                    df[self.volume_col].values, 
                    timeperiod=14
                )
            else:
                # Calculate MFI manually
                typical_price = (df[self.high_col] + df[self.low_col] + df[self.close_col]) / 3
                money_flow = typical_price * df[self.volume_col]
                
                positive_flow = money_flow.where(typical_price > typical_price.shift(1), 0)
                negative_flow = money_flow.where(typical_price < typical_price.shift(1), 0)
                
                positive_flow_sum = positive_flow.rolling(window=14).sum()
                negative_flow_sum = negative_flow.rolling(window=14).sum()
                
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
        if 'sma_20' in df.columns and 'sma_50' in df.columns:
            df['ma_signal'] = np.where(
                df[self.close_col] > df['sma_20'], 'buy',
                np.where(df[self.close_col] < df['sma_20'], 'sell', 'hold')
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
                        'message': f"RSI is oversold at {row['rsi']:.2f}",
                        'signal': 'buy',
                        'strength': min(1.0, (30 - row['rsi']) / 15)
                    })
                elif row['rsi'] > 70:
                    alerts.append({
                        'date': date,
                        'type': 'rsi_overbought',
                        'message': f"RSI is overbought at {row['rsi']:.2f}",
                        'signal': 'sell',
                        'strength': min(1.0, (row['rsi'] - 70) / 15)
                    })
            
            # MACD crosses
            if 'macd_cross_up' in row and row['macd_cross_up'] == 1:
                alerts.append({
                    'date': date,
                    'type': 'macd_cross_up',
                    'message': "MACD crossed above signal line (bullish)",
                    'signal': 'buy',
                    'strength': 0.8
                })
            elif 'macd_cross_down' in row and row['macd_cross_down'] == 1:
                alerts.append({
                    'date': date,
                    'type': 'macd_cross_down',
                    'message': "MACD crossed below signal line (bearish)",
                    'signal': 'sell',
                    'strength': 0.8
                })
            
            # Bollinger Band alerts
            if all(band in row for band in ['bb_upper', 'bb_lower']):
                if row[self.close_col] > row['bb_upper']:
                    alerts.append({
                        'date': date,
                        'type': 'bb_upper_breach',
                        'message': "Price broke above upper Bollinger Band (potential reversal or continuation)",
                        'signal': 'sell',
                        'strength': 0.7
                    })
                elif row[self.close_col] < row['bb_lower']:
                    alerts.append({
                        'date': date,
                        'type': 'bb_lower_breach',
                        'message': "Price broke below lower Bollinger Band (potential reversal or continuation)",
                        'signal': 'buy',
                        'strength': 0.7
                    })
            
            # Moving Average crosses
            if 'golden_cross' in row and row['golden_cross'] == 1:
                alerts.append({
                    'date': date,
                    'type': 'golden_cross',
                    'message': "Golden Cross: 50-day MA crossed above 200-day MA (bullish)",
                    'signal': 'buy',
                    'strength': 0.9
                })
            elif 'death_cross' in row and row['death_cross'] == 1:
                alerts.append({
                    'date': date,
                    'type': 'death_cross',
                    'message': "Death Cross: 50-day MA crossed below 200-day MA (bearish)",
                    'signal': 'sell',
                    'strength': 0.9
                })
            
            # Price crossing MA
            if 'sma_20' in row:
                price_cross_up = (row[self.close_col] > row['sma_20']) and (df.iloc[df.index.get_loc(idx)-1][self.close_col] <= df.iloc[df.index.get_loc(idx)-1]['sma_20'])
                price_cross_down = (row[self.close_col] < row['sma_20']) and (df.iloc[df.index.get_loc(idx)-1][self.close_col] >= df.iloc[df.index.get_loc(idx)-1]['sma_20'])
                
                if price_cross_up:
                    alerts.append({
                        'date': date,
                        'type': 'price_cross_ma_up',
                        'message': "Price crossed above 20-day moving average (bullish)",
                        'signal': 'buy',
                        'strength': 0.7
                    })
                elif price_cross_down:
                    alerts.append({
                        'date': date,
                        'type': 'price_cross_ma_down',
                        'message': "Price crossed below 20-day moving average (bearish)",
                        'signal': 'sell',
                        'strength': 0.7
                    })
            
            # Strong trend (ADX > 25)
            if 'adx' in row and 'plus_di' in row and 'minus_di' in row and row['adx'] > 25:
                if row['plus_di'] > row['minus_di']:
                    alerts.append({
                        'date': date,
                        'type': 'strong_uptrend',
                        'message': f"Strong uptrend detected (ADX: {row['adx']:.2f})",
                        'signal': 'buy',
                        'strength': min(1.0, row['adx'] / 50)
                    })
                else:
                    alerts.append({
                        'date': date,
                        'type': 'strong_downtrend',
                        'message': f"Strong downtrend detected (ADX: {row['adx']:.2f})",
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
    
    # Create technical indicators
    ti = TechnicalIndicators(df)
    df_with_indicators = ti.add_indicators()
    
    # Show some indicators
    print("\nTechnical Indicators Sample:")
    print(df_with_indicators[['close', 'rsi', 'macd', 'bb_upper', 'bb_lower', 'overall_signal']].tail())
    
    # Generate alerts
    alerts = ti.generate_alerts(lookback_period=5)
    
    print("\nRecent Alerts:")
    for alert in alerts:
        print(f"{alert['date']}: {alert['message']} ({alert['signal'].upper()} - Strength: {alert['strength']:.2f})")