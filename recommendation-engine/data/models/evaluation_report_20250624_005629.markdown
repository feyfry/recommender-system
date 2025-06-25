# Recommendation System Evaluation Report

Generated on: 2025-06-24 00:56:29

## Evaluation Details

- **Test Users:** 110
- **Total Evaluation Time:** 142.18 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2936 | 0.2930 | 0.2786 | 0.3600 | 0.8818 | 0.6304 |
| ncf | 0.2418 | 0.2420 | 0.2279 | 0.2711 | 0.7364 | 0.4783 |
| hybrid | 0.2982 | 0.2960 | 0.2822 | 0.3346 | 0.8318 | 0.5275 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1376±0.0078 | 0.4583±0.0260 | 0.2117±0.0120 | 0.3498±0.0134 | 0.6554±0.0301 | 5 |
| cold_start_hybrid | 0.1224±0.0035 | 0.4074±0.0116 | 0.1882±0.0054 | 0.2696±0.0157 | 0.5606±0.0430 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 7.12 |
| ncf | 10.20 |
| hybrid | 29.07 |
| cold_start_fecf | 23.01 |
| cold_start_hybrid | 70.90 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3927 | 0.1930 | 0.2481 | 0.4052 | 0.6727 |
| 10 | 0.2936 | 0.2930 | 0.2786 | 0.3600 | 0.8818 |
| 20 | 0.2364 | 0.4692 | 0.3010 | 0.4156 | 0.9500 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.2400 | 0.1134 | 0.1470 | 0.2544 | 0.5045 |
| 10 | 0.2418 | 0.2420 | 0.2279 | 0.2711 | 0.7364 |
| 20 | 0.2232 | 0.4406 | 0.2833 | 0.3477 | 0.8636 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3291 | 0.1626 | 0.2071 | 0.3288 | 0.5636 |
| 10 | 0.2982 | 0.2960 | 0.2822 | 0.3346 | 0.8318 |
| 20 | 0.2155 | 0.4221 | 0.2736 | 0.3674 | 0.9136 |
