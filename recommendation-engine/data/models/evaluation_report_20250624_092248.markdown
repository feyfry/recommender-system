# Recommendation System Evaluation Report

Generated on: 2025-06-24 09:22:48

## Evaluation Details

- **Test Users:** 110
- **Total Evaluation Time:** 197.95 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2809 | 0.2908 | 0.2697 | 0.3539 | 0.8727 | 0.6267 |
| ncf | 0.2600 | 0.2573 | 0.2468 | 0.2881 | 0.7227 | 0.4753 |
| hybrid | 0.3018 | 0.3038 | 0.2873 | 0.3455 | 0.8091 | 0.5657 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1451±0.0093 | 0.4821±0.0318 | 0.2230±0.0144 | 0.3678±0.0242 | 0.6751±0.0305 | 5 |
| cold_start_hybrid | 0.1276±0.0091 | 0.4242±0.0303 | 0.1962±0.0139 | 0.2981±0.0254 | 0.5761±0.0341 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 13.42 |
| ncf | 10.06 |
| hybrid | 34.34 |
| cold_start_fecf | 38.65 |
| cold_start_hybrid | 99.03 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3618 | 0.1898 | 0.2356 | 0.3817 | 0.6727 |
| 10 | 0.2809 | 0.2908 | 0.2697 | 0.3539 | 0.8727 |
| 20 | 0.2286 | 0.4623 | 0.2920 | 0.4094 | 0.9500 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.2673 | 0.1263 | 0.1655 | 0.2713 | 0.5000 |
| 10 | 0.2600 | 0.2573 | 0.2468 | 0.2881 | 0.7227 |
| 20 | 0.2159 | 0.4203 | 0.2746 | 0.3471 | 0.8909 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3091 | 0.1527 | 0.1961 | 0.3261 | 0.6045 |
| 10 | 0.3018 | 0.3038 | 0.2873 | 0.3455 | 0.8091 |
| 20 | 0.2173 | 0.4348 | 0.2769 | 0.3788 | 0.9273 |
