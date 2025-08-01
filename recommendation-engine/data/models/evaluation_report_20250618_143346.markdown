# Recommendation System Evaluation Report

Generated on: 2025-06-18 14:33:46

## Evaluation Details

- **Test Users:** 110
- **Total Evaluation Time:** 158.99 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2836 | 0.2660 | 0.2630 | 0.3310 | 0.8455 | 0.5683 |
| ncf | 0.2618 | 0.2565 | 0.2463 | 0.2966 | 0.8091 | 0.5341 |
| hybrid | 0.3145 | 0.2904 | 0.2895 | 0.3446 | 0.7864 | 0.5376 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1343±0.0085 | 0.4470±0.0291 | 0.2066±0.0132 | 0.3235±0.0315 | 0.6472±0.0425 | 5 |
| cold_start_hybrid | 0.1215±0.0111 | 0.4044±0.0379 | 0.1869±0.0172 | 0.2885±0.0333 | 0.5707±0.0494 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 5.56 |
| ncf | 8.90 |
| hybrid | 28.35 |
| cold_start_fecf | 30.28 |
| cold_start_hybrid | 84.14 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3655 | 0.1695 | 0.2227 | 0.3682 | 0.6091 |
| 10 | 0.2836 | 0.2660 | 0.2630 | 0.3310 | 0.8455 |
| 20 | 0.2323 | 0.4382 | 0.2909 | 0.3857 | 0.9409 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.2564 | 0.1276 | 0.1625 | 0.2762 | 0.5682 |
| 10 | 0.2618 | 0.2565 | 0.2463 | 0.2966 | 0.8091 |
| 20 | 0.2259 | 0.4316 | 0.2841 | 0.3651 | 0.9273 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3291 | 0.1558 | 0.2029 | 0.3341 | 0.6000 |
| 10 | 0.3145 | 0.2904 | 0.2895 | 0.3446 | 0.7864 |
| 20 | 0.2223 | 0.4120 | 0.2781 | 0.3697 | 0.9318 |
