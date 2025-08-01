# Recommendation System Evaluation Report

Generated on: 2025-06-17 17:37:19

## Evaluation Details

- **Test Users:** 141
- **Total Evaluation Time:** 224.55 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2099 | 0.3554 | 0.2451 | 0.3467 | 0.8085 | 0.5446 |
| ncf | 0.1567 | 0.2600 | 0.1819 | 0.2189 | 0.5780 | 0.3536 |
| hybrid | 0.2355 | 0.3896 | 0.2738 | 0.3396 | 0.7730 | 0.4564 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1307±0.0154 | 0.4337±0.0511 | 0.2008±0.0236 | 0.3249±0.0305 | 0.6373±0.0472 | 5 |
| cold_start_hybrid | 0.1176±0.0130 | 0.3899±0.0435 | 0.1806±0.0200 | 0.2604±0.0275 | 0.5371±0.0582 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 13.40 |
| ncf | 15.83 |
| hybrid | 44.00 |
| cold_start_fecf | 44.63 |
| cold_start_hybrid | 104.33 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3035 | 0.2555 | 0.2586 | 0.3406 | 0.5887 |
| 10 | 0.2099 | 0.3554 | 0.2451 | 0.3467 | 0.8085 |
| 20 | 0.1603 | 0.5351 | 0.2322 | 0.4155 | 0.9113 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.1532 | 0.1215 | 0.1257 | 0.1708 | 0.3652 |
| 10 | 0.1567 | 0.2600 | 0.1819 | 0.2189 | 0.5780 |
| 20 | 0.1443 | 0.4616 | 0.2071 | 0.3057 | 0.7553 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.2695 | 0.2318 | 0.2312 | 0.2871 | 0.4965 |
| 10 | 0.2355 | 0.3896 | 0.2738 | 0.3396 | 0.7730 |
| 20 | 0.1468 | 0.4922 | 0.2138 | 0.3720 | 0.9007 |
