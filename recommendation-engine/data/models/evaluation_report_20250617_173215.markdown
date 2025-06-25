# Recommendation System Evaluation Report

Generated on: 2025-06-17 17:32:15

## Evaluation Details

- **Test Users:** 19
- **Total Evaluation Time:** 125.97 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.3368 | 0.2403 | 0.2678 | 0.3425 | 0.8947 | 0.5005 |
| ncf | 0.3526 | 0.2579 | 0.2830 | 0.3564 | 0.8421 | 0.4888 |
| hybrid | 0.3842 | 0.2853 | 0.3112 | 0.4113 | 0.8947 | 0.6365 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1383±0.0142 | 0.4591±0.0476 | 0.2125±0.0219 | 0.3450±0.0359 | 0.6659±0.0436 | 5 |
| cold_start_hybrid | 0.1262±0.0155 | 0.4185±0.0522 | 0.1938±0.0239 | 0.2882±0.0402 | 0.5682±0.0489 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 1.21 |
| ncf | 1.71 |
| hybrid | 4.83 |
| cold_start_fecf | 26.92 |
| cold_start_hybrid | 89.87 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3684 | 0.1399 | 0.1907 | 0.3463 | 0.5789 |
| 10 | 0.3368 | 0.2403 | 0.2678 | 0.3425 | 0.8947 |
| 20 | 0.2763 | 0.3879 | 0.3092 | 0.3571 | 0.9737 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3474 | 0.1202 | 0.1712 | 0.3361 | 0.5263 |
| 10 | 0.3526 | 0.2579 | 0.2830 | 0.3564 | 0.8421 |
| 20 | 0.2711 | 0.4124 | 0.3103 | 0.3642 | 0.8947 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.4000 | 0.1486 | 0.2075 | 0.4026 | 0.6579 |
| 10 | 0.3842 | 0.2853 | 0.3112 | 0.4113 | 0.8947 |
| 20 | 0.2658 | 0.3785 | 0.2988 | 0.3831 | 0.9737 |
