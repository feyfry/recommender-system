"""
Modul untuk menghitung indikator-indikator teknikal (VERSI YANG DIPERBARUI - DENGAN PERIODE DINAMIS)
"""

import os
import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple, Any, Union
from pathlib import Path
import time
from datetime import datetime

# Import TA-Lib jika tersedia
try:
    import talib
    TALIB_AVAILABLE = True
except ImportError:
    TALIB_AVAILABLE = False
    logging.warning("TA-Lib tidak tersedia, menggunakan indikator berbasis pandas")

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
    Class untuk menghitung indikator-indikator teknikal dengan periode dinamis
    """
    
    def __init__(self, prices_df: pd.DataFrame, indicator_periods: Optional[Dict[str, Any]] = None):
        """
        Inisialisasi dengan DataFrame harga dan periode indikator kustom
        
        Args:
            prices_df: DataFrame dengan data harga (harus memiliki kolom 'close',
                      opsional 'high', 'low', 'volume')
            indicator_periods: Dictionary periode indikator kustom
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
            'willr_period': 14,
            'ichimoku_conversion': 9,
            'ichimoku_base': 26,
            'ichimoku_span_b': 52,
            'ichimoku_displacement': 26
        }
        
        # Update dengan periode kustom jika disediakan
        if indicator_periods is not None:
            for key, value in indicator_periods.items():
                if key in self.periods:
                    self.periods[key] = value
                    
        # Detect market regime
        self.market_regime = self._detect_market_regime()
        
        # Optimize parameters if no specific periods provided
        if indicator_periods is None:
            self._optimize_parameters()
                    
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
                   f"BB={self.periods['bb_period']}")
        
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
    
    def _detect_market_regime(self) -> str:
        """
        Deteksi regime pasar
        
        Returns:
            str: Market regime
        """
        # Implement market regime detection
        df = self.prices_df
        
        if len(df) < 30:
            return "unknown"  # Not enough data
        
        # Calculate recent returns
        if 'close' in df.columns:
            returns = df['close'].pct_change().dropna()
            
            # Volatility (annualized)
            recent_volatility = returns[-20:].std() * np.sqrt(252) if len(returns) >= 20 else 0
            
            # Direction (up/down)
            recent_return = df['close'].iloc[-1] / df['close'].iloc[-20] - 1 if len(df) >= 20 else 0
            
            # Determine regime based on volatility and direction
            if recent_volatility > 0.5:  # High volatility threshold for crypto
                if recent_return > 0.1:  # 10% up
                    return "volatile_bullish"
                elif recent_return < -0.1:  # 10% down
                    return "volatile_bearish"
                else:
                    return "volatile_sideways"
            else:
                if recent_return > 0.05:  # 5% up
                    return "trending_bullish"
                elif recent_return < -0.05:  # 5% down
                    return "trending_bearish"
                else:
                    return "ranging"
        
        return "unknown"
        
    def _optimize_parameters(self):
        """
        Optimize indicator parameters based on market regime and volatility
        """
        regime = self.market_regime
        
        if regime == "unknown":
            return  # Keep default parameters
            
        # Optimize based on regime
        if "volatile" in regime:
            # For volatile markets, use shorter periods to be more responsive
            volatility_factor = 0.7  # Reduce periods by 30%
            
            # Don't make periods too short
            self.periods['rsi_period'] = max(7, int(self.periods['rsi_period'] * volatility_factor))
            self.periods['macd_fast'] = max(6, int(self.periods['macd_fast'] * volatility_factor))
            self.periods['macd_slow'] = max(12, int(self.periods['macd_slow'] * volatility_factor))
            self.periods['bb_period'] = max(10, int(self.periods['bb_period'] * volatility_factor))
            self.periods['stoch_k'] = max(7, int(self.periods['stoch_k'] * volatility_factor))
            
        elif "trending" in regime:
            # For trending markets, use standard periods
            # Maybe optimize directional indicators
            if "bullish" in regime:
                # Slight optimization for bullish trend
                self.periods['macd_fast'] = max(8, int(self.periods['macd_fast'] * 0.8))
            elif "bearish" in regime:
                # Slight adjustments for bearish trend
                self.periods['rsi_period'] = max(10, int(self.periods['rsi_period'] * 0.9))
                
        elif "ranging" in regime:
            # For ranging markets, use longer periods to filter noise
            self.periods['rsi_period'] = min(21, int(self.periods['rsi_period'] * 1.2))
            self.periods['bb_period'] = min(30, int(self.periods['bb_period'] * 1.2))
            self.periods['stoch_k'] = min(21, int(self.periods['stoch_k'] * 1.2))
            
        logger.info(f"Parameters optimized for {regime} market regime")
    
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
            
            # Advanced indicators
            try:
                df = self.add_ichimoku_cloud(df)
            except Exception as e:
                logger.error(f"Error adding Ichimoku Cloud: {str(e)}")
                
            try:
                df = self.add_pivot_points(df)
            except Exception as e:
                logger.error(f"Error adding pivot points: {str(e)}")
            
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
        
        # MACD dengan periode dinamis
        macd_fast = self.periods['macd_fast']
        macd_slow = self.periods['macd_slow']
        macd_signal = self.periods['macd_signal']
        
        if len(df) >= macd_slow:
            if TALIB_AVAILABLE:
                try:
                    macd, signal, histogram = talib.MACD(
                        df[self.close_col].values, 
                        fastperiod=macd_fast, 
                        slowperiod=macd_slow, 
                        signalperiod=macd_signal
                    )
                    
                    df['macd'] = macd
                    df['macd_signal'] = signal
                    df['macd_hist'] = histogram
                except Exception as e:
                    logger.error(f"Error calculating MACD with TA-Lib: {str(e)}")
                    self._calculate_macd_pandas(df)
            else:
                self._calculate_macd_pandas(df)
        
        # ADX (Average Directional Index)
        adx_period = self.periods['adx_period']
        
        if len(df) >= adx_period and self.high_col in df.columns and self.low_col in df.columns:
            if TALIB_AVAILABLE:
                try:
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
                except Exception as e:
                    logger.error(f"Error calculating ADX with TA-Lib: {str(e)}")
                    self._calculate_adx_pandas(df)
            else:
                self._calculate_adx_pandas(df)
        
        # Parabolic SAR
        if len(df) >= 10 and self.high_col in df.columns and self.low_col in df.columns:
            if TALIB_AVAILABLE:
                try:
                    df['sar'] = talib.SAR(
                        df[self.high_col].values, 
                        df[self.low_col].values, 
                        acceleration=0.02, 
                        maximum=0.2
                    )
                except Exception as e:
                    logger.error(f"Error calculating SAR with TA-Lib: {str(e)}")
        
        # Add Moving Average Crossovers - penting untuk trading signals
        self._add_ma_crossovers(df)
        
        return df
    
    def _calculate_macd_pandas(self, df: pd.DataFrame) -> None:
        """Calculate MACD using pandas"""
        macd_fast = self.periods['macd_fast']
        macd_slow = self.periods['macd_slow']
        macd_signal = self.periods['macd_signal']
        
        try:
            # Calculate MACD dengan ewm
            fast_ema = df[self.close_col].ewm(span=macd_fast, adjust=False).mean()
            slow_ema = df[self.close_col].ewm(span=macd_slow, adjust=False).mean()
            
            df['macd'] = fast_ema - slow_ema
            df['macd_signal'] = df['macd'].ewm(span=macd_signal, adjust=False).mean()
            df['macd_hist'] = df['macd'] - df['macd_signal']
            
            # Calculate MACD crossover signals
            df['macd_cross_up'] = ((df['macd'] > df['macd_signal']) & 
                                   (df['macd'].shift(1) <= df['macd_signal'].shift(1))).astype(int)
            
            df['macd_cross_down'] = ((df['macd'] < df['macd_signal']) & 
                                    (df['macd'].shift(1) >= df['macd_signal'].shift(1))).astype(int)
        except Exception as e:
            logger.error(f"Error calculating MACD with pandas: {str(e)}")
    
    def _calculate_adx_pandas(self, df: pd.DataFrame) -> None:
        """Calculate ADX using pandas"""
        adx_period = self.periods['adx_period']
        
        try:
            # 1. True Range
            prev_close = df[self.close_col].shift(1)
            tr1 = df[self.high_col] - df[self.low_col]  # High - Low
            tr2 = (df[self.high_col] - prev_close).abs()  # |High - Previous Close|
            tr3 = (df[self.low_col] - prev_close).abs()  # |Low - Previous Close|
            
            true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
            atr = true_range.ewm(alpha=1/adx_period, adjust=False).mean()  # Wilder's smoothing
            
            # 2. Directional Movement
            up_move = df[self.high_col] - df[self.high_col].shift(1)
            down_move = df[self.low_col].shift(1) - df[self.low_col]
            
            # Plus Directional Movement (+DM)
            plus_dm = pd.Series(np.where((up_move > down_move) & (up_move > 0), up_move, 0), index=df.index)
            # Negative Directional Movement (-DM)
            minus_dm = pd.Series(np.where((down_move > up_move) & (down_move > 0), down_move, 0), index=df.index)
            
            # 3. Smoothed +DM and -DM
            plus_dm_ewm = plus_dm.ewm(alpha=1/adx_period, adjust=False).mean()
            minus_dm_ewm = minus_dm.ewm(alpha=1/adx_period, adjust=False).mean()
            
            # 4. +DI and -DI
            atr_safe = atr.replace(0, np.finfo(float).eps)  # Prevent division by zero
            plus_di = 100 * (plus_dm_ewm / atr_safe)
            minus_di = 100 * (minus_dm_ewm / atr_safe)
            
            # 5. DX and ADX
            di_diff = (plus_di - minus_di).abs()
            di_sum = plus_di + minus_di
            di_sum_safe = di_sum.replace(0, np.finfo(float).eps)
            dx = 100 * (di_diff / di_sum_safe)
            
            adx = dx.ewm(alpha=1/adx_period, adjust=False).mean()
            
            # Add to DataFrame
            df['adx'] = adx
            df['plus_di'] = plus_di
            df['minus_di'] = minus_di
        except Exception as e:
            logger.error(f"Error calculating ADX with pandas: {str(e)}")
    
    def _add_ma_crossovers(self, df: pd.DataFrame) -> None:
        """Add moving average crossover signals"""
        ma_short = self.periods['ma_short']
        ma_medium = self.periods['ma_medium']
        ma_long = self.periods['ma_long']
        
        # Short/Medium MA Cross
        if f'sma_{ma_short}' in df.columns and f'sma_{ma_medium}' in df.columns:
            df['sma_short_medium_cross'] = np.where(
                df[f'sma_{ma_short}'] > df[f'sma_{ma_medium}'], 1, -1
            )
            # Detect crosses
            df['sma_short_medium_cross_up'] = np.where(
                (df[f'sma_{ma_short}'] > df[f'sma_{ma_medium}']) & 
                (df[f'sma_{ma_short}'].shift(1) <= df[f'sma_{ma_medium}'].shift(1)), 
                1, 0
            )
            df['sma_short_medium_cross_down'] = np.where(
                (df[f'sma_{ma_short}'] < df[f'sma_{ma_medium}']) & 
                (df[f'sma_{ma_short}'].shift(1) >= df[f'sma_{ma_medium}'].shift(1)), 
                1, 0
            )
        
        # Golden Cross / Death Cross (Medium/Long MA Cross)
        if f'sma_{ma_medium}' in df.columns and f'sma_{ma_long}' in df.columns:
            df['sma_medium_long_cross'] = np.where(
                df[f'sma_{ma_medium}'] > df[f'sma_{ma_long}'], 1, -1
            )
            # Detect crosses
            df['golden_cross'] = np.where(
                (df[f'sma_{ma_medium}'] > df[f'sma_{ma_long}']) & 
                (df[f'sma_{ma_medium}'].shift(1) <= df[f'sma_{ma_long}'].shift(1)), 
                1, 0
            )
            df['death_cross'] = np.where(
                (df[f'sma_{ma_medium}'] < df[f'sma_{ma_long}']) & 
                (df[f'sma_{ma_medium}'].shift(1) >= df[f'sma_{ma_long}'].shift(1)), 
                1, 0
            )
            
        # Price/MA Crossovers
        for period in [ma_short, ma_medium, ma_long]:
            ma_col = f'sma_{period}'
            if ma_col in df.columns:
                cross_col = f'price_cross_{ma_col}'
                df[cross_col] = np.where(
                    (df[self.close_col] > df[ma_col]) & 
                    (df[self.close_col].shift(1) <= df[ma_col].shift(1)), 
                    1,  # Bullish cross
                    np.where(
                        (df[self.close_col] < df[ma_col]) & 
                        (df[self.close_col].shift(1) >= df[ma_col].shift(1)), 
                        -1,  # Bearish cross
                        0   # No cross
                    )
                )
    
    def add_momentum_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator momentum
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator momentum
        """
        # RSI - Relative Strength Index
        rsi_period = self.periods['rsi_period']
        
        if len(df) >= rsi_period:
            if TALIB_AVAILABLE:
                try:
                    df['rsi'] = talib.RSI(df[self.close_col].values, timeperiod=rsi_period)
                    
                    # Add RSI divergence
                    self._add_rsi_divergence(df)
                except Exception as e:
                    logger.error(f"Error calculating RSI with TA-Lib: {str(e)}")
                    self._calculate_rsi_pandas(df)
            else:
                self._calculate_rsi_pandas(df)
        
        # Stochastic Oscillator
        stoch_k = self.periods['stoch_k']
        stoch_d = self.periods['stoch_d']
        
        if len(df) >= stoch_k and self.high_col in df.columns and self.low_col in df.columns:
            if TALIB_AVAILABLE:
                try:
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
                    
                    # Add Stochastic crosses
                    df['stoch_cross_up'] = np.where(
                        (df['stoch_k'] > df['stoch_d']) & (df['stoch_k'].shift(1) <= df['stoch_d'].shift(1)),
                        1, 0
                    )
                    df['stoch_cross_down'] = np.where(
                        (df['stoch_k'] < df['stoch_d']) & (df['stoch_k'].shift(1) >= df['stoch_d'].shift(1)),
                        1, 0
                    )
                except Exception as e:
                    logger.error(f"Error calculating Stochastic with TA-Lib: {str(e)}")
                    self._calculate_stochastic_pandas(df)
            else:
                self._calculate_stochastic_pandas(df)
        
        # CCI - Commodity Channel Index
        cci_period = self.periods['cci_period']
        
        if len(df) >= cci_period and self.high_col in df.columns and self.low_col in df.columns:
            if TALIB_AVAILABLE:
                try:
                    df['cci'] = talib.CCI(
                        df[self.high_col].values, 
                        df[self.low_col].values, 
                        df[self.close_col].values, 
                        timeperiod=cci_period
                    )
                except Exception as e:
                    logger.error(f"Error calculating CCI with TA-Lib: {str(e)}")
                    self._calculate_cci_pandas(df)
            else:
                self._calculate_cci_pandas(df)
        
        # ROC - Rate of Change
        roc_period = self.periods['roc_period']
        
        if len(df) >= roc_period:
            if TALIB_AVAILABLE:
                try:
                    df['roc'] = talib.ROC(df[self.close_col].values, timeperiod=roc_period)
                except Exception as e:
                    logger.error(f"Error calculating ROC with TA-Lib: {str(e)}")
                    df['roc'] = df[self.close_col].pct_change(roc_period) * 100
            else:
                df['roc'] = df[self.close_col].pct_change(roc_period) * 100
        
        # Williams %R
        willr_period = self.periods['willr_period']
        
        if len(df) >= willr_period and self.high_col in df.columns and self.low_col in df.columns:
            if TALIB_AVAILABLE:
                try:
                    df['willr'] = talib.WILLR(
                        df[self.high_col].values, 
                        df[self.low_col].values, 
                        df[self.close_col].values, 
                        timeperiod=willr_period
                    )
                except Exception as e:
                    logger.error(f"Error calculating Williams %R with TA-Lib: {str(e)}")
                    self._calculate_willr_pandas(df)
            else:
                self._calculate_willr_pandas(df)
        
        # MFI - Money Flow Index (jika volume tersedia)
        if self.volume_col in df.columns and len(df) >= rsi_period:
            if TALIB_AVAILABLE:
                try:
                    df['mfi'] = talib.MFI(
                        df[self.high_col].values,
                        df[self.low_col].values,
                        df[self.close_col].values,
                        df[self.volume_col].values,
                        timeperiod=rsi_period
                    )
                except Exception as e:
                    logger.error(f"Error calculating MFI with TA-Lib: {str(e)}")
        
        # Add Combined Momentum Indicator
        self._add_momentum_composite(df)
        
        return df
    
    def _calculate_rsi_pandas(self, df: pd.DataFrame) -> None:
        """Calculate RSI using pandas"""
        rsi_period = self.periods['rsi_period']
        
        try:
            # Calculate up and down moves
            delta = df[self.close_col].diff()
            gain = delta.where(delta > 0, 0)
            loss = -delta.where(delta < 0, 0)
            
            # First average
            avg_gain = gain.rolling(window=rsi_period).mean()
            avg_loss = loss.rolling(window=rsi_period).mean()
            
            # Get first valid values (to mimic Wilder's smoothing)
            first_valid_index = min(avg_gain.first_valid_index(), avg_loss.first_valid_index())
            
            # Prepare RSI series
            rsi = pd.Series(index=df.index)
            
            # Calculate for first valid point
            if first_valid_index is not None:
                avg_gain_first = avg_gain.loc[first_valid_index]
                avg_loss_first = avg_loss.loc[first_valid_index]
                
                # Apply smoothing formula for remaining points
                for i in range(df.index.get_loc(first_valid_index) + 1, len(df)):
                    current_gain = gain.iloc[i]
                    current_loss = loss.iloc[i]
                    
                    # Wilder's smoothing
                    avg_gain_first = (avg_gain_first * (rsi_period - 1) + current_gain) / rsi_period
                    avg_loss_first = (avg_loss_first * (rsi_period - 1) + current_loss) / rsi_period
                    
                    # Calculate RS and RSI
                    if avg_loss_first != 0:
                        rs = avg_gain_first / avg_loss_first
                    else:
                        rs = 100  # Avoid division by zero
                        
                    rsi.iloc[i] = 100 - (100 / (1 + rs))
            
            # Add to dataframe
            df['rsi'] = rsi
            
            # Add simple RSI overbought/oversold signals
            df['rsi_overbought'] = df['rsi'] > 70
            df['rsi_oversold'] = df['rsi'] < 30
        except Exception as e:
            logger.error(f"Error calculating RSI with pandas: {str(e)}")
    
    def _add_rsi_divergence(self, df: pd.DataFrame) -> None:
        """Add RSI divergence signals"""
        try:
            # Find local highs and lows in price
            df['price_higher_high'] = (df[self.close_col] > df[self.close_col].shift(1)) & (df[self.close_col] > df[self.close_col].shift(-1))
            df['price_lower_low'] = (df[self.close_col] < df[self.close_col].shift(1)) & (df[self.close_col] < df[self.close_col].shift(-1))
            
            # Find local highs and lows in RSI
            df['rsi_higher_high'] = (df['rsi'] > df['rsi'].shift(1)) & (df['rsi'] > df['rsi'].shift(-1))
            df['rsi_lower_low'] = (df['rsi'] < df['rsi'].shift(1)) & (df['rsi'] < df['rsi'].shift(-1))
            
            # Bearish divergence: price makes higher high but RSI makes lower high
            df['bearish_divergence'] = (df['price_higher_high']) & (df['rsi'] < df['rsi'].shift(2)) & (df['rsi'] > 70)
            
            # Bullish divergence: price makes lower low but RSI makes higher low
            df['bullish_divergence'] = (df['price_lower_low']) & (df['rsi'] > df['rsi'].shift(2)) & (df['rsi'] < 30)
        except Exception as e:
            logger.error(f"Error calculating RSI divergence: {str(e)}")
    
    def _calculate_stochastic_pandas(self, df: pd.DataFrame) -> None:
        """Calculate Stochastic Oscillator using pandas"""
        stoch_k = self.periods['stoch_k']
        stoch_d = self.periods['stoch_d']
        
        try:
            # Calculate %K - Fast Stochastic Oscillator
            low_min = df[self.low_col].rolling(window=stoch_k).min()
            high_max = df[self.high_col].rolling(window=stoch_k).max()
            
            # Avoid division by zero
            range_diff = high_max - low_min
            range_diff = range_diff.replace(0, np.finfo(float).eps)
            
            # %K = (Current Close - Lowest Low) / (Highest High - Lowest Low) * 100
            k = ((df[self.close_col] - low_min) / range_diff) * 100
            
            # Calculate %D - Slow Stochastic Oscillator (3-period SMA of %K)
            d = k.rolling(window=stoch_d).mean()
            
            # Add to dataframe
            df['stoch_k'] = k
            df['stoch_d'] = d
            
            # Add Stochastic crossover signals
            df['stoch_cross_up'] = np.where(
                (df['stoch_k'] > df['stoch_d']) & (df['stoch_k'].shift(1) <= df['stoch_d'].shift(1)),
                1, 0
            )
            df['stoch_cross_down'] = np.where(
                (df['stoch_k'] < df['stoch_d']) & (df['stoch_k'].shift(1) >= df['stoch_d'].shift(1)),
                1, 0
            )
            
            # Add overbought/oversold signals
            df['stoch_overbought'] = df['stoch_k'] > 80
            df['stoch_oversold'] = df['stoch_k'] < 20
        except Exception as e:
            logger.error(f"Error calculating Stochastic with pandas: {str(e)}")
    
    def _calculate_cci_pandas(self, df: pd.DataFrame) -> None:
        """Calculate Commodity Channel Index using pandas"""
        cci_period = self.periods['cci_period']
        
        try:
            # Calculate typical price
            df['tp'] = (df[self.high_col] + df[self.low_col] + df[self.close_col]) / 3
            
            # Calculate moving average of typical price
            df['tp_sma'] = df['tp'].rolling(window=cci_period).mean()
            
            # Calculate mean deviation
            df['tp_md'] = df['tp'].rolling(window=cci_period).apply(
                lambda x: pd.Series(x).mad()  # Mean absolute deviation
            )
            
            # Avoid division by zero
            df['tp_md'] = df['tp_md'].replace(0, np.finfo(float).eps)
            
            # Calculate CCI
            df['cci'] = (df['tp'] - df['tp_sma']) / (0.015 * df['tp_md'])
            
            # Clean up temporary columns
            df.drop(['tp', 'tp_sma', 'tp_md'], axis=1, inplace=True)
        except Exception as e:
            logger.error(f"Error calculating CCI with pandas: {str(e)}")
    
    def _calculate_willr_pandas(self, df: pd.DataFrame) -> None:
        """Calculate Williams %R using pandas"""
        willr_period = self.periods['willr_period']
        
        try:
            # Calculate highest high and lowest low over period
            highest_high = df[self.high_col].rolling(window=willr_period).max()
            lowest_low = df[self.low_col].rolling(window=willr_period).min()
            
            # Avoid division by zero
            range_diff = highest_high - lowest_low
            range_diff = range_diff.replace(0, np.finfo(float).eps)
            
            # Calculate Williams %R
            # %R = (Highest High - Close) / (Highest High - Lowest Low) * -100
            williams_r = ((highest_high - df[self.close_col]) / range_diff) * -100
            
            # Add to dataframe
            df['willr'] = williams_r
        except Exception as e:
            logger.error(f"Error calculating Williams %R with pandas: {str(e)}")
    
    def _add_momentum_composite(self, df: pd.DataFrame) -> None:
        """Add composite momentum indicator from multiple momentum indicators"""
        try:
            # Normalize indicators to 0-100 scale
            indicators = []
            weights = []
            
            # RSI is already 0-100
            if 'rsi' in df.columns:
                indicators.append(df['rsi'])
                weights.append(0.25)
            
            # Stochastic %K is already 0-100
            if 'stoch_k' in df.columns:
                indicators.append(df['stoch_k'])
                weights.append(0.20)
            
            # CCI needs to be normalized
            if 'cci' in df.columns:
                # Normalize CCI: 100 to +100 maps to 0 to 100
                cci_norm = (df['cci'] + 100) / 2
                # Clip to 0-100 range
                cci_norm = cci_norm.clip(0, 100)
                indicators.append(cci_norm)
                weights.append(0.15)
            
            # Williams %R is -100 to 0, needs to be inverted and normalized
            if 'willr' in df.columns:
                # Convert -100 to 0 range to 0 to 100 range
                willr_norm = -df['willr']
                indicators.append(willr_norm)
                weights.append(0.15)
            
            # ROC needs to be normalized - we'll use a simple sigmoid
            if 'roc' in df.columns:
                # Apply sigmoid-like transformation
                # Map ROC between -10 and +10 to 0-100
                roc_norm = (df['roc'] + 10) * 5
                # Clip to 0-100 range
                roc_norm = roc_norm.clip(0, 100)
                indicators.append(roc_norm)
                weights.append(0.15)
            
            # MFI is already 0-100
            if 'mfi' in df.columns:
                indicators.append(df['mfi'])
                weights.append(0.10)
            
            # Calculate weighted average if we have indicators
            if indicators:
                # Normalize weights
                weights_sum = sum(weights)
                weights = [w / weights_sum for w in weights]
                
                # Calculate composite
                df['momentum_composite'] = 0
                for i, indicator in enumerate(indicators):
                    df['momentum_composite'] += indicator * weights[i]
                
                # Add signal based on composite
                df['momentum_signal'] = np.where(
                    df['momentum_composite'] > 70, 'overbought',
                    np.where(df['momentum_composite'] < 30, 'oversold', 'neutral')
                )
        except Exception as e:
            logger.error(f"Error calculating momentum composite: {str(e)}")
    
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
                try:
                    df['bb_upper'], df['bb_middle'], df['bb_lower'] = talib.BBANDS(
                        df[self.close_col].values, 
                        timeperiod=bb_period, 
                        nbdevup=2, 
                        nbdevdn=2, 
                        matype=0
                    )
                    
                    # Calculate %B and Bandwidth
                    if not df['bb_upper'].isna().all() and not df['bb_lower'].isna().all():
                        range_diff = df['bb_upper'] - df['bb_lower']
                        range_diff = range_diff.replace(0, np.finfo(float).eps)
                        
                        df['bb_pct_b'] = (df[self.close_col] - df['bb_lower']) / range_diff
                        df['bb_bandwidth'] = range_diff / df['bb_middle']
                except Exception as e:
                    logger.error(f"Error calculating Bollinger Bands with TA-Lib: {str(e)}")
                    self._calculate_bollinger_bands_pandas(df)
            else:
                self._calculate_bollinger_bands_pandas(df)
        
        # ATR - Average True Range
        atr_period = self.periods['atr_period']
        
        if len(df) >= atr_period and self.high_col in df.columns and self.low_col in df.columns:
            if TALIB_AVAILABLE:
                try:
                    df['atr'] = talib.ATR(
                        df[self.high_col].values, 
                        df[self.low_col].values, 
                        df[self.close_col].values, 
                        timeperiod=atr_period
                    )
                    
                    # Add ATR percentage (ATR as % of price)
                    df['atr_pct'] = (df['atr'] / df[self.close_col]) * 100
                except Exception as e:
                    logger.error(f"Error calculating ATR with TA-Lib: {str(e)}")
                    self._calculate_atr_pandas(df)
            else:
                self._calculate_atr_pandas(df)
        
        # Keltner Channels
        if len(df) >= atr_period and 'atr' in df.columns:
            try:
                # Calculate Keltner Channels using EMA and ATR
                ema = df[self.close_col].ewm(span=bb_period, adjust=False).mean()
                df['keltner_middle'] = ema
                df['keltner_upper'] = ema + (df['atr'] * 2)
                df['keltner_lower'] = ema - (df['atr'] * 2)
                
                # Calculate price position within Keltner Channels
                range_diff = df['keltner_upper'] - df['keltner_lower']
                range_diff = range_diff.replace(0, np.finfo(float).eps)
                
                df['keltner_pct'] = (df[self.close_col] - df['keltner_lower']) / range_diff
            except Exception as e:
                logger.error(f"Error calculating Keltner Channels: {str(e)}")
        
        # Volatility calculation (annualized standard deviation of returns)
        try:
            # Daily returns
            df['daily_return'] = df[self.close_col].pct_change()
            
            # 21-day historical volatility (annualized)
            df['volatility_21d'] = df['daily_return'].rolling(window=21).std() * np.sqrt(252)
            
            # 63-day historical volatility (annualized)
            df['volatility_63d'] = df['daily_return'].rolling(window=63).std() * np.sqrt(252)
        except Exception as e:
            logger.error(f"Error calculating historical volatility: {str(e)}")
        
        # Add Squeezes (Bollinger Bands inside Keltner Channels)
        self._add_volatility_squeeze(df)
        
        return df
    
    def _calculate_bollinger_bands_pandas(self, df: pd.DataFrame) -> None:
        """Calculate Bollinger Bands using pandas"""
        bb_period = self.periods['bb_period']
        
        try:
            # Calculate middle band - Simple Moving Average
            df['bb_middle'] = df[self.close_col].rolling(window=bb_period).mean()
            
            # Calculate standard deviation
            df['bb_std'] = df[self.close_col].rolling(window=bb_period).std()
            
            # Calculate upper and lower bands
            df['bb_upper'] = df['bb_middle'] + (2 * df['bb_std'])
            df['bb_lower'] = df['bb_middle'] - (2 * df['bb_std'])
            
            # Calculate %B = (Price - Lower Band) / (Upper Band - Lower Band)
            range_diff = df['bb_upper'] - df['bb_lower']
            range_diff = range_diff.replace(0, np.finfo(float).eps)
            
            df['bb_pct_b'] = (df[self.close_col] - df['bb_lower']) / range_diff
            
            # Calculate Bandwidth = (Upper Band - Lower Band) / Middle Band
            df['bb_bandwidth'] = range_diff / df['bb_middle']
        except Exception as e:
            logger.error(f"Error calculating Bollinger Bands with pandas: {str(e)}")
    
    def _calculate_atr_pandas(self, df: pd.DataFrame) -> None:
        """Calculate Average True Range using pandas"""
        atr_period = self.periods['atr_period']
        
        try:
            # True Range calculation
            tr1 = df[self.high_col] - df[self.low_col]
            tr2 = (df[self.high_col] - df[self.close_col].shift(1)).abs()
            tr3 = (df[self.low_col] - df[self.close_col].shift(1)).abs()
            
            # True Range is the max of the three
            df['tr'] = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
            
            # Calculate ATR using Wilder's Smoothing method
            df['atr'] = df['tr'].ewm(alpha=1/atr_period, adjust=False).mean()
            
            # Calculate ATR percentage
            df['atr_pct'] = (df['atr'] / df[self.close_col]) * 100
            
            # Drop temporary column
            df.drop(['tr'], axis=1, inplace=True)
        except Exception as e:
            logger.error(f"Error calculating ATR with pandas: {str(e)}")
    
    def _add_volatility_squeeze(self, df: pd.DataFrame) -> None:
        """Add volatility squeeze indicator (when Bollinger Bands are inside Keltner Channels)"""
        if not all(col in df.columns for col in ['bb_upper', 'bb_lower', 'keltner_upper', 'keltner_lower']):
            return
            
        try:
            # Bollinger Bands inside Keltner Channels = Squeeze (low volatility)
            df['squeeze_on'] = (df['bb_upper'] < df['keltner_upper']) & (df['bb_lower'] > df['keltner_lower'])
            
            # Squeeze coming on (starting to get constricted)
            df['squeeze_starting'] = df['squeeze_on'] & (~df['squeeze_on'].shift(1).fillna(False))
            
            # Squeeze releasing (ending constriction)
            df['squeeze_releasing'] = (~df['squeeze_on']) & (df['squeeze_on'].shift(1).fillna(False))
            
            # Add additional volatility status
            # Calculate Bollinger Bandwidth quartiles
            if 'bb_bandwidth' in df.columns:
                bandwidth_median = df['bb_bandwidth'].median()
                bandwidth_q1 = df['bb_bandwidth'].quantile(0.25)
                bandwidth_q3 = df['bb_bandwidth'].quantile(0.75)
                
                df['volatility_state'] = np.where(
                    df['bb_bandwidth'] < bandwidth_q1, 'low',
                    np.where(df['bb_bandwidth'] > bandwidth_q3, 'high', 'normal')
                )
        except Exception as e:
            logger.error(f"Error calculating volatility squeeze: {str(e)}")
    
    def add_volume_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator volume
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator volume
        """
        if self.volume_col is None or self.volume_col not in df.columns:
            logger.warning("Volume column not available, skipping volume indicators")
            return df
        
        try:
            # On Balance Volume (OBV)
            if TALIB_AVAILABLE:
                try:
                    df['obv'] = talib.OBV(df[self.close_col].values, df[self.volume_col].values)
                except Exception as e:
                    logger.error(f"Error calculating OBV with TA-Lib: {str(e)}")
                    self._calculate_obv_pandas(df)
            else:
                self._calculate_obv_pandas(df)
            
            # Volume Moving Averages
            ma_short = self.periods['ma_short']
            ma_medium = self.periods['ma_medium']
            
            df[f'volume_sma_{ma_short}'] = df[self.volume_col].rolling(window=ma_short).mean()
            df[f'volume_sma_{ma_medium}'] = df[self.volume_col].rolling(window=ma_medium).mean()
            
            # Volume Ratio (current volume to average volume)
            df['volume_ratio'] = df[self.volume_col] / df[f'volume_sma_{ma_medium}']
            
            # Chaikin Money Flow (CMF)
            cmf_period = 20  # Standard period
            if len(df) >= cmf_period and all(col in df.columns for col in [self.high_col, self.low_col]):
                try:
                    # Money Flow Multiplier
                    mfm = ((df[self.close_col] - df[self.low_col]) - (df[self.high_col] - df[self.close_col])) / (df[self.high_col] - df[self.low_col])
                    
                    # Handle division by zero
                    mfm = mfm.replace([np.inf, -np.inf], 0)
                    mfm = mfm.fillna(0)
                    
                    # Money Flow Volume
                    mfv = mfm * df[self.volume_col]
                    
                    # Chaikin Money Flow
                    df['cmf'] = mfv.rolling(window=cmf_period).sum() / df[self.volume_col].rolling(window=cmf_period).sum()
                except Exception as e:
                    logger.error(f"Error calculating Chaikin Money Flow: {str(e)}")
            
            # Accumulation/Distribution Line
            if self.high_col in df.columns and self.low_col in df.columns:
                try:
                    # Money Flow Multiplier
                    mfm = ((df[self.close_col] - df[self.low_col]) - (df[self.high_col] - df[self.close_col])) / (df[self.high_col] - df[self.low_col])
                    
                    # Handle division by zero
                    mfm = mfm.replace([np.inf, -np.inf], 0)
                    mfm = mfm.fillna(0)
                    
                    # Money Flow Volume
                    mfv = mfm * df[self.volume_col]
                    
                    # A/D Line
                    df['ad_line'] = mfv.cumsum()
                except Exception as e:
                    logger.error(f"Error calculating Accumulation/Distribution Line: {str(e)}")
            
            # Volume Oscillator (Percentage Volume Oscillator - PVO)
            if len(df) >= ma_medium:
                try:
                    ema_short = df[self.volume_col].ewm(span=12, adjust=False).mean()
                    ema_long = df[self.volume_col].ewm(span=26, adjust=False).mean()
                    
                    # PVO = ((12-day EMA - 26-day EMA) / 26-day EMA) * 100
                    df['pvo'] = ((ema_short - ema_long) / ema_long) * 100
                    
                    # PVO Signal Line (9-day EMA of PVO)
                    df['pvo_signal'] = df['pvo'].ewm(span=9, adjust=False).mean()
                    
                    # PVO Histogram
                    df['pvo_hist'] = df['pvo'] - df['pvo_signal']
                except Exception as e:
                    logger.error(f"Error calculating Volume Oscillator: {str(e)}")
            
            # Detect volume spikes
            self._add_volume_spike_detection(df)
            
            # Add volume divergence
            self._add_volume_divergence(df)
            
        except Exception as e:
            logger.error(f"Error adding volume indicators: {str(e)}")
        
        return df
    
    def _calculate_obv_pandas(self, df: pd.DataFrame) -> None:
        """Calculate On Balance Volume using pandas"""
        try:
            # Price direction
            price_direction = np.sign(df[self.close_col].diff())
            
            # Calculate OBV
            df['obv'] = (df[self.volume_col] * price_direction).fillna(0).cumsum()
        except Exception as e:
            logger.error(f"Error calculating OBV with pandas: {str(e)}")
    
    def _add_volume_spike_detection(self, df: pd.DataFrame) -> None:
        """Add volume spike detection indicators"""
        try:
            # Calculate volume moving average
            vol_ma = df[self.volume_col].rolling(window=20).mean()
            
            # Calculate volume standard deviation
            vol_std = df[self.volume_col].rolling(window=20).std()
            
            # Define volume spike threshold (e.g., 2 standard deviations above mean)
            spike_threshold = vol_ma + (2 * vol_std)
            
            # Detect spikes
            df['volume_spike'] = df[self.volume_col] > spike_threshold
            
            # Add additional context: up day or down day with spike
            df['volume_spike_up'] = df['volume_spike'] & (df[self.close_col] > df[self.close_col].shift(1))
            df['volume_spike_down'] = df['volume_spike'] & (df[self.close_col] < df[self.close_col].shift(1))
        except Exception as e:
            logger.error(f"Error adding volume spike detection: {str(e)}")
    
    def _add_volume_divergence(self, df: pd.DataFrame) -> None:
        """Add volume-price divergence indicators"""
        try:
            # Get price and volume trends
            if 'obv' in df.columns:
                # Use 5-day slope for trends
                price_slope = df[self.close_col].diff(5)
                obv_slope = df['obv'].diff(5)
                
                # Bullish divergence: price falling but OBV rising (accumulation)
                df['bullish_vol_div'] = (price_slope < 0) & (obv_slope > 0)
                
                # Bearish divergence: price rising but OBV falling (distribution)
                df['bearish_vol_div'] = (price_slope > 0) & (obv_slope < 0)
        except Exception as e:
            logger.error(f"Error adding volume divergence: {str(e)}")
    
    def add_ichimoku_cloud(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Add Ichimoku Cloud indicators
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator Ichimoku
        """
        if not all(col in df.columns for col in [self.high_col, self.low_col]):
            return df  # Need high/low data for Ichimoku
        
        # Get Ichimoku parameters
        conversion_period = self.periods.get('ichimoku_conversion', 9)
        base_period = self.periods.get('ichimoku_base', 26)
        span_b_period = self.periods.get('ichimoku_span_b', 52)
        displacement = self.periods.get('ichimoku_displacement', 26)
        
        try:
            # Tenkan-sen (Conversion Line): (highest high + lowest low) / 2 for past 9 periods
            high_conv = df[self.high_col].rolling(window=conversion_period).max()
            low_conv = df[self.low_col].rolling(window=conversion_period).min()
            df['tenkan_sen'] = (high_conv + low_conv) / 2
            
            # Kijun-sen (Base Line): (highest high + lowest low) / 2 for past 26 periods
            high_base = df[self.high_col].rolling(window=base_period).max()
            low_base = df[self.low_col].rolling(window=base_period).min()
            df['kijun_sen'] = (high_base + low_base) / 2
            
            # Senkou Span A (Leading Span A): (Conversion Line + Base Line) / 2, plotted 26 periods ahead
            df['senkou_span_a'] = ((df['tenkan_sen'] + df['kijun_sen']) / 2).shift(displacement)
            
            # Senkou Span B (Leading Span B): (highest high + lowest low) / 2 for past 52 periods, plotted 26 periods ahead
            high_span_b = df[self.high_col].rolling(window=span_b_period).max()
            low_span_b = df[self.low_col].rolling(window=span_b_period).min()
            df['senkou_span_b'] = ((high_span_b + low_span_b) / 2).shift(displacement)
            
            # Chikou Span (Lagging Span): Close price plotted 26 periods behind
            df['chikou_span'] = df[self.close_col].shift(-displacement)
            
            # Add cloud color/type
            df['cloud_green'] = df['senkou_span_a'] > df['senkou_span_b']
            df['cloud_red'] = df['senkou_span_a'] < df['senkou_span_b']
            
            # Add price position relative to cloud
            df['price_above_cloud'] = df[self.close_col] > df[['senkou_span_a', 'senkou_span_b']].max(axis=1)
            df['price_in_cloud'] = (df[self.close_col] >= df[['senkou_span_a', 'senkou_span_b']].min(axis=1)) & \
                                  (df[self.close_col] <= df[['senkou_span_a', 'senkou_span_b']].max(axis=1))
            df['price_below_cloud'] = df[self.close_col] < df[['senkou_span_a', 'senkou_span_b']].min(axis=1)
            
            # Add Tenkan/Kijun Cross signals
            df['tk_cross_bull'] = (df['tenkan_sen'] > df['kijun_sen']) & (df['tenkan_sen'].shift(1) <= df['kijun_sen'].shift(1))
            df['tk_cross_bear'] = (df['tenkan_sen'] < df['kijun_sen']) & (df['tenkan_sen'].shift(1) >= df['kijun_sen'].shift(1))
            
        except Exception as e:
            logger.error(f"Error adding Ichimoku Cloud: {str(e)}")
        
        return df
    
    def add_pivot_points(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Add pivot points - commonly used for support/resistance levels
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan pivot points
        """
        if not all(col in df.columns for col in [self.high_col, self.low_col]):
            return df  # Need high/low data for pivot points
        
        try:
            # Traditional pivot point
            df['pivot'] = (df[self.high_col].shift(1) + df[self.low_col].shift(1) + df[self.close_col].shift(1)) / 3
            
            # Support levels
            df['support1'] = (df['pivot'] * 2) - df[self.high_col].shift(1)
            df['support2'] = df['pivot'] - (df[self.high_col].shift(1) - df[self.low_col].shift(1))
            df['support3'] = df[self.low_col].shift(1) - 2 * (df[self.high_col].shift(1) - df['pivot'])
            
            # Resistance levels
            df['resistance1'] = (df['pivot'] * 2) - df[self.low_col].shift(1)
            df['resistance2'] = df['pivot'] + (df[self.high_col].shift(1) - df[self.low_col].shift(1))
            df['resistance3'] = df[self.high_col].shift(1) + 2 * (df['pivot'] - df[self.low_col].shift(1))
            
            # Identify if price is testing pivot levels
            df['at_pivot'] = (df[self.close_col] > df['pivot'] * 0.995) & (df[self.close_col] < df['pivot'] * 1.005)
            df['at_support1'] = (df[self.close_col] > df['support1'] * 0.995) & (df[self.close_col] < df['support1'] * 1.005)
            df['at_resistance1'] = (df[self.close_col] > df['resistance1'] * 0.995) & (df[self.close_col] < df['resistance1'] * 1.005)
            
        except Exception as e:
            logger.error(f"Error adding pivot points: {str(e)}")
        
        return df
    
    def add_signal_indicators(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Tambahkan indikator sinyal/keputusan
        
        Args:
            df: DataFrame harga
            
        Returns:
            pd.DataFrame: DataFrame dengan indikator sinyal
        """
        try:
            # Add RSI signals
            if 'rsi' in df.columns:
                df['rsi_signal'] = np.where(
                    df['rsi'] < 30, 'buy',
                    np.where(df['rsi'] > 70, 'sell', 'neutral')
                )
            
            # Add MACD signals
            if all(col in df.columns for col in ['macd', 'macd_signal']):
                df['macd_signal_type'] = np.where(
                    df['macd_cross_up'] == 1, 'strong_buy',
                    np.where(df['macd_cross_down'] == 1, 'strong_sell',
                            np.where(df['macd'] > df['macd_signal'], 'buy', 'sell'))
                )
            
            # Add Bollinger Bands signals
            if all(col in df.columns for col in ['bb_upper', 'bb_lower']):
                df['bb_signal'] = np.where(
                    df[self.close_col] < df['bb_lower'], 'buy',
                    np.where(df[self.close_col] > df['bb_upper'], 'sell', 'neutral')
                )
            
            # Add Stochastic signals
            if 'stoch_k' in df.columns and 'stoch_d' in df.columns:
                df['stoch_signal'] = np.where(
                    (df['stoch_k'] < 20) & (df['stoch_cross_up'] == 1), 'strong_buy',
                    np.where((df['stoch_k'] > 80) & (df['stoch_cross_down'] == 1), 'strong_sell',
                            np.where(df['stoch_k'] < 20, 'buy',
                                    np.where(df['stoch_k'] > 80, 'sell', 'neutral')))
                )
            
            # Add MA signals
            df['ma_signal'] = 'neutral'
            
            ma_short = self.periods['ma_short']
            ma_medium = self.periods['ma_medium']
            ma_long = self.periods['ma_long']
            
            # Check if MA columns exist before using them
            if f'sma_{ma_long}' in df.columns:
                # Golden/Death Cross
                if 'golden_cross' in df.columns and 'death_cross' in df.columns:
                    df.loc[df['golden_cross'] == 1, 'ma_signal'] = 'strong_buy'
                    df.loc[df['death_cross'] == 1, 'ma_signal'] = 'strong_sell'
                
                # Price vs MA alignment
                if all(col in df.columns for col in [f'sma_{ma_short}', f'sma_{ma_medium}', f'sma_{ma_long}']):
                    # Strong bullish alignment: Price > Short MA > Medium MA > Long MA
                    bullish_cond = (df[self.close_col] > df[f'sma_{ma_short}']) & \
                                  (df[f'sma_{ma_short}'] > df[f'sma_{ma_medium}']) & \
                                  (df[f'sma_{ma_medium}'] > df[f'sma_{ma_long}'])
                    
                    # Strong bearish alignment: Price < Short MA < Medium MA < Long MA
                    bearish_cond = (df[self.close_col] < df[f'sma_{ma_short}']) & \
                                  (df[f'sma_{ma_short}'] < df[f'sma_{ma_medium}']) & \
                                  (df[f'sma_{ma_medium}'] < df[f'sma_{ma_long}'])
                    
                    df.loc[bullish_cond, 'ma_signal'] = 'buy'
                    df.loc[bearish_cond, 'ma_signal'] = 'sell'
            
            # Add Ichimoku signals
            if all(col in df.columns for col in ['tenkan_sen', 'kijun_sen']):
                df['ichimoku_signal'] = 'neutral'
                
                # TK Cross above cloud (very bullish)
                tk_cross_bull_above = df['tk_cross_bull'] & df['price_above_cloud']
                df.loc[tk_cross_bull_above, 'ichimoku_signal'] = 'strong_buy'
                
                # TK Cross below cloud (very bearish)
                tk_cross_bear_below = df['tk_cross_bear'] & df['price_below_cloud']
                df.loc[tk_cross_bear_below, 'ichimoku_signal'] = 'strong_sell'
                
                # Price above green cloud (bullish)
                price_above_green = df['price_above_cloud'] & df['cloud_green']
                df.loc[price_above_green, 'ichimoku_signal'] = 'buy'
                
                # Price below red cloud (bearish)
                price_below_red = df['price_below_cloud'] & df['cloud_red']
                df.loc[price_below_red, 'ichimoku_signal'] = 'sell'
            
            # Combined Signal - weighted voting
            # Count buying and selling signals
            signal_columns = [col for col in df.columns if col.endswith('_signal') 
                           and not col.endswith('_signal_type')]
            
            if signal_columns:
                # Initialize counters
                df['buy_signals'] = 0
                df['sell_signals'] = 0
                df['strong_buy_signals'] = 0
                df['strong_sell_signals'] = 0
                df['neutral_signals'] = 0
                
                # Count signals with proper weights
                for signal_col in signal_columns:
                    df.loc[df[signal_col] == 'buy', 'buy_signals'] += 1
                    df.loc[df[signal_col] == 'sell', 'sell_signals'] += 1
                    df.loc[df[signal_col] == 'strong_buy', 'strong_buy_signals'] += 1
                    df.loc[df[signal_col] == 'strong_sell', 'strong_sell_signals'] += 1
                    df.loc[df[signal_col] == 'neutral', 'neutral_signals'] += 1
                
                # Calculate weighted buy/sell strength
                total_signals = len(signal_columns)
                
                df['buy_strength'] = (df['buy_signals'] + df['strong_buy_signals'] * 2) / (total_signals * 2)
                df['sell_strength'] = (df['sell_signals'] + df['strong_sell_signals'] * 2) / (total_signals * 2)
                
                # Overall signal based on weighted strength
                df['overall_signal'] = np.where(
                    (df['buy_strength'] > df['sell_strength']) & (df['buy_strength'] > 0.5), 'buy',
                    np.where((df['sell_strength'] > df['buy_strength']) & (df['sell_strength'] > 0.5), 'sell', 'hold')
                )
        except Exception as e:
            logger.error(f"Error adding signal indicators: {str(e)}")
        
        return df
    
    def generate_alerts(self, lookback_period: int = 5) -> List[Dict[str, Any]]:
        """
        Generate alerts based on technical indicators
        
        Args:
            lookback_period: Number of periods to look back for alerts
            
        Returns:
            list: List of alert dictionaries
        """
        # First add indicators if not already added
        if not hasattr(self, 'df_with_indicators'):
            self.df_with_indicators = self.add_indicators()
        else:
            df = self.df_with_indicators
            
        # Get the last n rows
        try:
            recent_data = df.iloc[-lookback_period:]
        except:
            # Fall back to all data if slicing fails
            recent_data = df
        
        alerts = []
        
        # Check for various alert conditions
        for idx, row in recent_data.iterrows():
            date = idx if isinstance(idx, pd.Timestamp) else pd.Timestamp(idx)
            
            # Strong trend change
            if 'adx' in row and row['adx'] > 30:
                if 'plus_di' in row and 'minus_di' in row:
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
            
            # RSI overbought/oversold
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
            
            # Bollinger Band breaks
            if all(band in row for col in ['bb_upper', 'bb_lower']):
                if row[self.close_col] > row['bb_upper']:
                    alerts.append({
                        'date': date,
                        'type': 'bb_upper_break',
                        'message': f"Harga menembus di atas upper Bollinger Band (overbought) - periode {self.periods['bb_period']}",
                        'signal': 'sell',
                        'strength': 0.7
                    })
                elif row[self.close_col] < row['bb_lower']:
                    alerts.append({
                        'date': date,
                        'type': 'bb_lower_break',
                        'message': f"Harga menembus di bawah lower Bollinger Band (oversold) - periode {self.periods['bb_period']}",
                        'signal': 'buy',
                        'strength': 0.7
                    })
            
            # MA crosses
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
            
            # Ichimoku signals
            if all(col in row for col in ['tk_cross_bull', 'price_above_cloud']):
                if row['tk_cross_bull'] and row['price_above_cloud']:
                    alerts.append({
                        'date': date,
                        'type': 'ichimoku_bullish',
                        'message': f"Sinyal Ichimoku sangat bullish: Tenkan-Kijun Cross di atas cloud",
                        'signal': 'buy',
                        'strength': 0.9
                    })
                elif row['tk_cross_bear'] and row['price_below_cloud']:
                    alerts.append({
                        'date': date,
                        'type': 'ichimoku_bearish',
                        'message': f"Sinyal Ichimoku sangat bearish: Tenkan-Kijun Cross di bawah cloud",
                        'signal': 'sell',
                        'strength': 0.9
                    })
            
            # Volume spikes with price movement
            if 'volume_spike_up' in row and row['volume_spike_up']:
                alerts.append({
                    'date': date,
                    'type': 'volume_spike_up',
                    'message': f"Lonjakan volume dengan kenaikan harga (konfirmasi tren naik)",
                    'signal': 'buy',
                    'strength': 0.7
                })
            elif 'volume_spike_down' in row and row['volume_spike_down']:
                alerts.append({
                    'date': date,
                    'type': 'volume_spike_down',
                    'message': f"Lonjakan volume dengan penurunan harga (konfirmasi tren turun)",
                    'signal': 'sell',
                    'strength': 0.7
                })
            
            # RSI divergence
            if 'bullish_divergence' in row and row['bullish_divergence']:
                alerts.append({
                    'date': date,
                    'type': 'bullish_divergence',
                    'message': f"Divergensi bullish: harga membuat lower low tapi RSI membuat higher low",
                    'signal': 'buy',
                    'strength': 0.8
                })
            elif 'bearish_divergence' in row and row['bearish_divergence']:
                alerts.append({
                    'date': date,
                    'type': 'bearish_divergence',
                    'message': f"Divergensi bearish: harga membuat higher high tapi RSI membuat lower high",
                    'signal': 'sell',
                    'strength': 0.8
                })
            
            # Volatility Squeeze releasing
            if 'squeeze_releasing' in row and row['squeeze_releasing']:
                # Check direction
                if 'momentum_composite' in row:
                    if row['momentum_composite'] > 50:
                        alerts.append({
                            'date': date,
                            'type': 'squeeze_bullish',
                            'message': f"Squeeze momentum bullish releasing (potensi breakout naik)",
                            'signal': 'buy',
                            'strength': 0.75
                        })
                    else:
                        alerts.append({
                            'date': date,
                            'type': 'squeeze_bearish',
                            'message': f"Squeeze momentum bearish releasing (potensi breakout turun)",
                            'signal': 'sell',
                            'strength': 0.75
                        })
        
        # Sort alerts by date (most recent first)
        alerts.sort(key=lambda x: x['date'], reverse=True)
        
        return alerts
    
    def generate_reversal_signals(self) -> Dict[str, Any]:
        """
        Generate potential reversal signals
        
        Returns:
            dict: Potential reversal signals and probabilities
        """
        # Add indicators if not already
        df = self.add_indicators()
        
        # Cannot generate signals with less than 20 data points
        if len(df) < 20:
            return {
                "reversal_probability": 0.0,
                "direction": "unknown",
                "signals": []
            }
        
        reversal_signals = []
        bullish_score = 0
        bearish_score = 0
        
        # Check last row for signals
        row = df.iloc[-1]
        
        # 1. RSI divergence
        if 'bullish_divergence' in df.columns and row['bullish_divergence']:
            reversal_signals.append({
                "type": "bullish_divergence",
                "description": "Bullish RSI divergence detected",
                "strength": 0.8
            })
            bullish_score += 0.8
        
        if 'bearish_divergence' in df.columns and row['bearish_divergence']:
            reversal_signals.append({
                "type": "bearish_divergence",
                "description": "Bearish RSI divergence detected",
                "strength": 0.8
            })
            bearish_score += 0.8
        
        # 2. Bollinger Band touch or break
        if 'bb_lower' in df.columns and 'bb_upper' in df.columns:
            if row[self.close_col] <= row['bb_lower']:
                reversal_signals.append({
                    "type": "bb_lower_touch",
                    "description": "Price touched/broke lower Bollinger Band (potential bullish reversal)",
                    "strength": 0.7
                })
                bullish_score += 0.7
            
            if row[self.close_col] >= row['bb_upper']:
                reversal_signals.append({
                    "type": "bb_upper_touch",
                    "description": "Price touched/broke upper Bollinger Band (potential bearish reversal)",
                    "strength": 0.7
                })
                bearish_score += 0.7
        
        # 3. Stochastic oversold/overbought with divergence
        if 'stoch_k' in df.columns and 'stoch_d' in df.columns:
            # Oversold condition
            if row['stoch_k'] < 20 and row['stoch_d'] < 20:
                # Check if stochastic is turning up
                if row['stoch_k'] > row['stoch_k_prev'] if 'stoch_k_prev' in row else row['stoch_k'] > df.iloc[-2]['stoch_k']:
                    reversal_signals.append({
                        "type": "stoch_oversold_bullish",
                        "description": "Stochastic oversold and turning up (bullish)",
                        "strength": 0.65
                    })
                    bullish_score += 0.65
            
            # Overbought condition
            if row['stoch_k'] > 80 and row['stoch_d'] > 80:
                # Check if stochastic is turning down
                if row['stoch_k'] < row['stoch_k_prev'] if 'stoch_k_prev' in row else row['stoch_k'] < df.iloc[-2]['stoch_k']:
                    reversal_signals.append({
                        "type": "stoch_overbought_bearish",
                        "description": "Stochastic overbought and turning down (bearish)",
                        "strength": 0.65
                    })
                    bearish_score += 0.65
        
        # 4. Volume analysis
        if 'volume_ratio' in df.columns:
            # High volume on potential reversal
            if row['volume_ratio'] > 1.5:
                if 'rsi' in df.columns:
                    if row['rsi'] < 30:
                        reversal_signals.append({
                            "type": "high_volume_oversold",
                            "description": "High volume at oversold levels (potential bullish reversal)",
                            "strength": 0.6
                        })
                        bullish_score += 0.6
                    elif row['rsi'] > 70:
                        reversal_signals.append({
                            "type": "high_volume_overbought",
                            "description": "High volume at overbought levels (potential bearish reversal)",
                            "strength": 0.6
                        })
                        bearish_score += 0.6
        
        # 5. Candlestick patterns (basic check)
        if all(col in df.columns for col in [self.open_col, self.high_col, self.low_col, self.close_col]):
            # Hammer (bullish)
            body = abs(row[self.close_col] - row[self.open_col])
            lower_wick = min(row[self.open_col], row[self.close_col]) - row[self.low_col]
            upper_wick = row[self.high_col] - max(row[self.open_col], row[self.close_col])
            
            # Hammer pattern (small body, long lower shadow, little/no upper shadow)
            if lower_wick > body * 2 and upper_wick < body * 0.5:
                reversal_signals.append({
                    "type": "hammer",
                    "description": "Hammer candlestick pattern detected (potential bullish reversal)",
                    "strength": 0.6
                })
                bullish_score += 0.6
            
            # Shooting star (bearish)
            if upper_wick > body * 2 and lower_wick < body * 0.5:
                reversal_signals.append({
                    "type": "shooting_star",
                    "description": "Shooting star candlestick pattern detected (potential bearish reversal)",
                    "strength": 0.6
                })
                bearish_score += 0.6
        
        # Calculate overall probability and direction
        total_score = bullish_score + bearish_score
        
        if total_score == 0:
            probability = 0.0
            direction = "unknown"
        else:
            if bullish_score > bearish_score:
                probability = bullish_score / (total_score * 2)  # Normalize to 0-0.5
                probability += 0.5  # Adjust to 0.5-1.0 range for bullish
                direction = "bullish"
            else:
                probability = bearish_score / (total_score * 2)  # Normalize to 0-0.5
                probability += 0.5  # Adjust to 0.5-1.0 range for bearish
                direction = "bearish"
        
        return {
            "reversal_probability": probability,
            "direction": direction,
            "signals": reversal_signals
        }
    
    def predict_price_trend(self, periods: int = 5) -> Dict[str, Any]:
        """
        Predict price trend for the near future
        
        Args:
            periods: Number of periods to predict
            
        Returns:
            dict: Prediction data including direction, confidence, target levels
        """
        df = self.add_indicators()
        
        # Need sufficient data points
        if len(df) < 30:
            return {
                "error": "Insufficient data for prediction",
                "trend": "unknown",
                "confidence": 0.0
            }
        
        try:
            # Current price
            current_price = df[self.close_col].iloc[-1]
            
            # Determine trend direction
            if 'adx' in df.columns and 'plus_di' in df.columns and 'minus_di' in df.columns:
                adx = df['adx'].iloc[-1]
                plus_di = df['plus_di'].iloc[-1]
                minus_di = df['minus_di'].iloc[-1]
                
                trend_strength = min(1.0, adx / 50)  # Normalize 0-1
                
                if plus_di > minus_di:
                    primary_trend = "up"
                else:
                    primary_trend = "down"
            else:
                # Fallback to simple MA trend
                ma_short = df[f'sma_{self.periods["ma_short"]}'].iloc[-1] if f'sma_{self.periods["ma_short"]}' in df.columns else None
                ma_long = df[f'sma_{self.periods["ma_long"]}'].iloc[-1] if f'sma_{self.periods["ma_long"]}' in df.columns else None
                
                if ma_short and ma_long:
                    if ma_short > ma_long:
                        primary_trend = "up"
                        trend_strength = 0.6  # Medium confidence
                    else:
                        primary_trend = "down"
                        trend_strength = 0.6  # Medium confidence
                else:
                    # Last resort - use recent price changes
                    recent_change = df[self.close_col].pct_change(5).iloc[-1]
                    
                    if recent_change > 0:
                        primary_trend = "up"
                        trend_strength = 0.5  # Lower confidence
                    else:
                        primary_trend = "down"
                        trend_strength = 0.5  # Lower confidence
            
            # Potential reversal signals
            reversal_data = self.generate_reversal_signals()
            reversal_probability = reversal_data["reversal_probability"]
            
            # Final trend prediction - combine primary trend with reversal probability
            if reversal_probability > 0.7:  # Strong reversal signals
                if primary_trend == "up":
                    trend = "down"  # Reverse the trend
                    confidence = reversal_probability - 0.3  # Adjust confidence
                else:
                    trend = "up"  # Reverse the trend
                    confidence = reversal_probability - 0.3  # Adjust confidence
            else:
                trend = primary_trend
                confidence = max(0.3, trend_strength - (reversal_probability - 0.5))  # Reduce confidence based on reversal probability
            
            # Calculate target levels
            if 'atr' in df.columns:
                atr = df['atr'].iloc[-1]
                
                if trend == "up":
                    target_1 = current_price + atr
                    target_2 = current_price + (atr * 2)
                    support_1 = current_price - (atr * 0.5)
                    support_2 = current_price - atr
                else:  # down
                    target_1 = current_price - atr
                    target_2 = current_price - (atr * 2)
                    support_1 = current_price + (atr * 0.5)
                    support_2 = current_price + atr
            else:
                # Estimate targets based on recent volatility
                volatility = df[self.close_col].pct_change().std() * current_price
                
                if trend == "up":
                    target_1 = current_price + volatility
                    target_2 = current_price + (volatility * 2)
                    support_1 = current_price - (volatility * 0.5)
                    support_2 = current_price - volatility
                else:  # down
                    target_1 = current_price - volatility
                    target_2 = current_price - (volatility * 2)
                    support_1 = current_price + (volatility * 0.5)
                    support_2 = current_price + volatility
            
            return {
                "trend": trend,
                "confidence": confidence,
                "current_price": current_price,
                "target_1": target_1,
                "target_2": target_2,
                "support_1": support_1,
                "support_2": support_2,
                "time_frame": f"{periods} periods",
                "reversal_probability": reversal_probability,
                "reversal_signals": reversal_data["signals"]
            }
            
        except Exception as e:
            logger.error(f"Error predicting price trend: {str(e)}")
            return {
                "error": f"Error predicting price trend: {str(e)}",
                "trend": "unknown",
                "confidence": 0.0
            }


# Helper functions for ML-based price predictions

def predict_price_ml(prices_df: pd.DataFrame, days_to_predict: int = 7) -> Dict[str, Any]:
    """
    Predict future prices using machine learning (LSTM)
    
    Args:
        prices_df: DataFrame with historical price data
        days_to_predict: Number of days to predict
        
    Returns:
        dict: Prediction results
    """
    try:
        # Check if we have TensorFlow for LSTM
        try:
            import tensorflow as tf
            from tensorflow.keras.models import Sequential
            from tensorflow.keras.layers import LSTM, Dense, Dropout
            from sklearn.preprocessing import MinMaxScaler
            
            has_tensorflow = True
        except ImportError:
            has_tensorflow = False
            logger.warning("TensorFlow not available, falling back to statistical model")
            return predict_price_arima(prices_df, days_to_predict)
        
        # Verify we have enough data
        if len(prices_df) < 60:
            logger.warning("Not enough data for ML prediction, falling back to statistical model")
            return predict_price_arima(prices_df, days_to_predict)
        
        # Prepare data for LSTM
        data = prices_df['close'].values.reshape(-1, 1)
        
        # Scale the data
        scaler = MinMaxScaler(feature_range=(0, 1))
        scaled_data = scaler.fit_transform(data)
        
        # Create features and target
        time_steps = 60  # Using 60 days to predict
        
        # Initialize arrays
        X = []
        y = []
        
        # Create sequences
        for i in range(time_steps, len(scaled_data)):
            X.append(scaled_data[i-time_steps:i, 0])
            y.append(scaled_data[i, 0])
            
        # Convert to numpy arrays
        X = np.array(X)
        y = np.array(y)
        
        # Reshape for LSTM
        X = np.reshape(X, (X.shape[0], X.shape[1], 1))
        
        # Build LSTM model
        model = Sequential()
        model.add(LSTM(units=50, return_sequences=True, input_shape=(X.shape[1], 1)))
        model.add(Dropout(0.2))
        model.add(LSTM(units=50))
        model.add(Dropout(0.2))
        model.add(Dense(units=1))
        
        # Compile and fit model
        model.compile(optimizer='adam', loss='mean_squared_error')
        model.fit(X, y, epochs=25, batch_size=32, verbose=0)
        
        # Get the last sequence for prediction
        last_sequence = scaled_data[-time_steps:]
        
        # Initialize prediction array
        predictions = []
        current_sequence = last_sequence.copy()
        
        # Make predictions
        for i in range(days_to_predict):
            # Reshape for prediction
            current_input = current_sequence[-time_steps:].reshape(1, time_steps, 1)
            
            # Predict next value
            next_value = model.predict(current_input, verbose=0)[0, 0]
            
            # Append to predictions
            predictions.append(next_value)
            
            # Update current sequence
            current_sequence = np.append(current_sequence, next_value)
            current_sequence = current_sequence[1:]
            
        # Inverse transform the predictions
        predictions_reshaped = np.array(predictions).reshape(-1, 1)
        predictions_inverse = scaler.inverse_transform(predictions_reshaped)
        
        # Create result dictionary
        dates = pd.date_range(
            start=prices_df.index[-1] + pd.Timedelta(days=1), 
            periods=days_to_predict, 
            freq='D'
        )
        
        prediction_data = []
        
        for i in range(len(predictions_inverse)):
            prediction_data.append({
                'date': dates[i].strftime('%Y-%m-%d'),
                'value': float(predictions_inverse[i, 0]),
                'confidence': max(0.1, 0.9 - (i * 0.05))  # Confidence decreases with time
            })
        
        # Determine trend direction
        last_price = prices_df['close'].iloc[-1]
        final_prediction = prediction_data[-1]['value']
        
        if final_prediction > last_price:
            trend = "up"
            change_pct = (final_prediction / last_price - 1) * 100
        else:
            trend = "down"
            change_pct = (1 - final_prediction / last_price) * 100
        
        return {
            'current_price': float(last_price),
            'prediction_data': prediction_data,
            'trend': trend,
            'change_percent': float(change_pct),
            'model_type': 'LSTM',
            'confidence': 0.7  # LSTM is generally more confident than statistical models
        }
        
    except Exception as e:
        logger.error(f"Error in ML price prediction: {str(e)}")
        # Fall back to ARIMA model
        return predict_price_arima(prices_df, days_to_predict)


def predict_price_arima(prices_df: pd.DataFrame, days_to_predict: int = 7) -> Dict[str, Any]:
    """
    Predict future prices using ARIMA statistical model
    
    Args:
        prices_df: DataFrame with historical price data
        days_to_predict: Number of days to predict
        
    Returns:
        dict: Prediction results
    """
    try:
        # Try to use statsmodels for ARIMA
        try:
            from statsmodels.tsa.arima.model import ARIMA
            has_statsmodels = True
        except ImportError:
            has_statsmodels = False
            logger.warning("statsmodels not available, falling back to simple forecast")
            return predict_price_simple(prices_df, days_to_predict)
        
        # Prepare data
        price_series = prices_df['close']
        
        # Fit ARIMA model - try simple (1,1,1) for stability
        model = ARIMA(price_series, order=(1, 1, 1))
        model_fit = model.fit()
        
        # Make forecast
        forecast = model_fit.forecast(steps=days_to_predict)
        
        # Create result dictionary
        dates = pd.date_range(
            start=prices_df.index[-1] + pd.Timedelta(days=1), 
            periods=days_to_predict, 
            freq='D'
        )
        
        prediction_data = []
        
        for i in range(len(forecast)):
            prediction_data.append({
                'date': dates[i].strftime('%Y-%m-%d'),
                'value': float(forecast[i]),
                'confidence': max(0.1, 0.7 - (i * 0.05))  # Confidence decreases with time
            })
        
        # Determine trend direction
        last_price = prices_df['close'].iloc[-1]
        final_prediction = prediction_data[-1]['value']
        
        if final_prediction > last_price:
            trend = "up"
            change_pct = (final_prediction / last_price - 1) * 100
        else:
            trend = "down"
            change_pct = (1 - final_prediction / last_price) * 100
        
        return {
            'current_price': float(last_price),
            'prediction_data': prediction_data,
            'trend': trend,
            'change_percent': float(change_pct),
            'model_type': 'ARIMA',
            'confidence': 0.5  # Medium confidence for statistical model
        }
        
    except Exception as e:
        logger.error(f"Error in ARIMA price prediction: {str(e)}")
        # Fall back to simple model
        return predict_price_simple(prices_df, days_to_predict)


def predict_price_simple(prices_df: pd.DataFrame, days_to_predict: int = 7) -> Dict[str, Any]:
    """
    Simple price prediction using moving average and recent trend
    
    Args:
        prices_df: DataFrame with historical price data
        days_to_predict: Number of days to predict
        
    Returns:
        dict: Prediction results
    """
    try:
        # Calculate moving averages
        if len(prices_df) >= 20:
            prices_df['sma_5'] = prices_df['close'].rolling(window=5).mean()
            prices_df['sma_20'] = prices_df['close'].rolling(window=20).mean()
            
            # Calculate recent trend
            trend_factor = prices_df['sma_5'].iloc[-1] / prices_df['sma_20'].iloc[-1] - 1
        else:
            # Not enough data for MA, use simple trend
            trend_factor = prices_df['close'].pct_change(min(5, len(prices_df)-1)).iloc[-1]
        
        # Adjust trend factor to be reasonable
        trend_factor = min(0.03, max(-0.03, trend_factor))  # Limit to ±3% per day
        
        # Current price
        last_price = prices_df['close'].iloc[-1]
        
        # Calculate volatility
        if len(prices_df) >= 10:
            volatility = prices_df['close'].pct_change().std()
        else:
            volatility = 0.02  # Default 2% for crypto
        
        # Generate predictions
        dates = pd.date_range(
            start=prices_df.index[-1] + pd.Timedelta(days=1), 
            periods=days_to_predict, 
            freq='D'
        )
        
        prediction_data = []
        
        # Initialize with current price
        current_pred = last_price
        
        for i in range(days_to_predict):
            # Add trend and random component
            random_factor = np.random.normal(0, volatility * current_pred)
            next_pred = current_pred * (1 + trend_factor) + random_factor
            
            # Ensure positive
            next_pred = max(0.001, next_pred)
            
            prediction_data.append({
                'date': dates[i].strftime('%Y-%m-%d'),
                'value': float(next_pred),
                'confidence': max(0.1, 0.5 - (i * 0.05))  # Confidence decreases with time
            })
            
            # Update current prediction for next iteration
            current_pred = next_pred
        
        # Determine trend direction
        final_prediction = prediction_data[-1]['value']
        
        if final_prediction > last_price:
            trend = "up"
            change_pct = (final_prediction / last_price - 1) * 100
        else:
            trend = "down"
            change_pct = (1 - final_prediction / last_price) * 100
        
        return {
            'current_price': float(last_price),
            'prediction_data': prediction_data,
            'trend': trend,
            'change_percent': float(change_pct),
            'model_type': 'Simple',
            'confidence': 0.3  # Lower confidence for simple model
        }
        
    except Exception as e:
        logger.error(f"Error in simple price prediction: {str(e)}")
        return {
            'error': f"Prediction failed: {str(e)}",
            'current_price': float(prices_df['close'].iloc[-1]) if 'close' in prices_df.columns else 0,
            'trend': 'unknown',
            'confidence': 0.1
        }


if __name__ == "__main__":
    # Test the module with demo data
    import yfinance as yf
    
    # Download sample data
    try:
        print("Downloading sample data for BTC-USD...")
        data = yf.download("BTC-USD", period="6mo")
        
        print(f"Downloaded {len(data)} rows of data")
        
        # Rename columns to match expected format
        data = data.rename(columns={
            'Open': 'open',
            'High': 'high',
            'Low': 'low',
            'Close': 'close',
            'Volume': 'volume'
        })
        
        # Test standard parameters
        print("\n=== Testing with standard parameters ===")
        indicators = TechnicalIndicators(data)
        df_indicators = indicators.add_indicators()
        
        print(f"Generated {len(df_indicators.columns)} indicators")
        
        # Test with short term parameters
        print("\n=== Testing with short term parameters ===")
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
        indicators_short = TechnicalIndicators(data, short_term)
        df_short = indicators_short.add_indicators()
        
        print(f"Generated {len(df_short.columns)} indicators with short term parameters")
        
        # Generate alerts
        alerts = indicators.generate_alerts(lookback_period=5)
        
        print(f"\nGenerated {len(alerts)} alerts:")
        for alert in alerts[:3]:  # Show top 3
            print(f"- {alert['date']}: {alert['message']} ({alert['signal']})")
        
        # Test price prediction
        prediction = indicators.predict_price_trend(periods=5)
        
        print("\nPrice prediction:")
        print(f"Trend: {prediction['trend']}")
        print(f"Confidence: {prediction['confidence']:.2f}")
        print(f"Current price: ${prediction['current_price']:.2f}")
        print(f"Target 1: ${prediction['target_1']:.2f}")
        print(f"Target 2: ${prediction['target_2']:.2f}")
        
        # Test ML-based prediction
        print("\nML-based price prediction:")
        ml_prediction = predict_price_ml(data, days_to_predict=7)
        
        print(f"Model type: {ml_prediction['model_type']}")
        print(f"Trend: {ml_prediction['trend']} ({ml_prediction['change_percent']:.2f}%)")
        print(f"Confidence: {ml_prediction['confidence']:.2f}")
        print(f"7-day prediction: ${ml_prediction['prediction_data'][-1]['value']:.2f}")
        
    except Exception as e:
        print(f"Error testing indicators: {str(e)}")