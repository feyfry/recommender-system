# Recommendation System Evaluation Report

Generated on: 2025-06-23 23:15:08

## Evaluation Details

- **Test Users:** 110
- **Total Evaluation Time:** 128.79 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2391 | 0.2664 | 0.2383 | 0.2971 | 0.8318 | 0.5336 |
| ncf | 0.2455 | 0.2586 | 0.2395 | 0.2696 | 0.7500 | 0.4379 |
| hybrid | 0.2909 | 0.3164 | 0.2873 | 0.3378 | 0.8409 | 0.5403 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1414±0.0069 | 0.4699±0.0233 | 0.2173±0.0107 | 0.3493±0.0106 | 0.6754±0.0339 | 5 |
| cold_start_hybrid | 0.1250±0.0086 | 0.4156±0.0288 | 0.1921±0.0133 | 0.2945±0.0309 | 0.5673±0.0223 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 5.56 |
| ncf | 9.62 |
| hybrid | 25.43 |
| cold_start_fecf | 19.34 |
| cold_start_hybrid | 67.40 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3218 | 0.1805 | 0.2188 | 0.3265 | 0.5909 |
| 10 | 0.2391 | 0.2664 | 0.2383 | 0.2971 | 0.8318 |
| 20 | 0.2077 | 0.4558 | 0.2732 | 0.3720 | 0.9591 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.2618 | 0.1307 | 0.1691 | 0.2534 | 0.4818 |
| 10 | 0.2455 | 0.2586 | 0.2395 | 0.2696 | 0.7500 |
| 20 | 0.2123 | 0.4363 | 0.2753 | 0.3436 | 0.8682 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3382 | 0.1769 | 0.2225 | 0.3344 | 0.6000 |
| 10 | 0.2909 | 0.3164 | 0.2873 | 0.3378 | 0.8409 |
| 20 | 0.1936 | 0.4130 | 0.2528 | 0.3613 | 0.9682 |
