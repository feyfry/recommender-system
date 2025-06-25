# Recommendation System Evaluation Report

Generated on: 2025-06-16 10:01:25

## Evaluation Details

- **Test Users:** 109
- **Total Evaluation Time:** 150.14 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2596 | 0.2714 | 0.2487 | 0.3236 | 0.8211 | 0.5671 |
| ncf | 0.2569 | 0.2634 | 0.2467 | 0.2865 | 0.7569 | 0.4652 |
| hybrid | 0.2670 | 0.2747 | 0.2552 | 0.3203 | 0.7798 | 0.5453 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1288±0.0153 | 0.4276±0.0511 | 0.1979±0.0236 | 0.3181±0.0422 | 0.6379±0.0451 | 5 |
| cold_start_hybrid | 0.0997±0.0121 | 0.3316±0.0411 | 0.1533±0.0187 | 0.2705±0.0402 | 0.5687±0.0643 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 10.80 |
| ncf | 7.76 |
| hybrid | 23.99 |
| cold_start_fecf | 25.91 |
| cold_start_hybrid | 79.94 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3303 | 0.1768 | 0.2170 | 0.3437 | 0.6101 |
| 10 | 0.2596 | 0.2714 | 0.2487 | 0.3236 | 0.8211 |
| 20 | 0.2032 | 0.4165 | 0.2593 | 0.3669 | 0.9541 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.2624 | 0.1391 | 0.1727 | 0.2631 | 0.5000 |
| 10 | 0.2569 | 0.2634 | 0.2467 | 0.2865 | 0.7569 |
| 20 | 0.2119 | 0.4333 | 0.2711 | 0.3488 | 0.8853 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3138 | 0.1625 | 0.2030 | 0.3236 | 0.5917 |
| 10 | 0.2670 | 0.2747 | 0.2552 | 0.3203 | 0.7798 |
| 20 | 0.2087 | 0.4270 | 0.2664 | 0.3646 | 0.9266 |
