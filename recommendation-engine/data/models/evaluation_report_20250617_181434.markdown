# Recommendation System Evaluation Report

Generated on: 2025-06-17 18:14:34

## Evaluation Details

- **Test Users:** 109
- **Total Evaluation Time:** 176.01 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2661 | 0.2660 | 0.2480 | 0.3322 | 0.8165 | 0.5871 |
| ncf | 0.2248 | 0.2226 | 0.2098 | 0.2503 | 0.6835 | 0.4372 |
| hybrid | 0.2835 | 0.2858 | 0.2657 | 0.3257 | 0.8211 | 0.4995 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1336±0.0194 | 0.4420±0.0650 | 0.2050±0.0299 | 0.3255±0.0487 | 0.6292±0.0749 | 5 |
| cold_start_hybrid | 0.1180±0.0180 | 0.3902±0.0608 | 0.1810±0.0278 | 0.2658±0.0397 | 0.5345±0.0659 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 6.79 |
| ncf | 10.34 |
| hybrid | 30.21 |
| cold_start_fecf | 26.77 |
| cold_start_hybrid | 100.15 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3486 | 0.1833 | 0.2233 | 0.3690 | 0.6330 |
| 10 | 0.2661 | 0.2660 | 0.2480 | 0.3322 | 0.8165 |
| 20 | 0.2087 | 0.4242 | 0.2641 | 0.3761 | 0.9404 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.2239 | 0.1076 | 0.1368 | 0.2320 | 0.4771 |
| 10 | 0.2248 | 0.2226 | 0.2098 | 0.2503 | 0.6835 |
| 20 | 0.1954 | 0.3925 | 0.2464 | 0.3102 | 0.8394 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3321 | 0.1698 | 0.2110 | 0.3293 | 0.5459 |
| 10 | 0.2835 | 0.2858 | 0.2657 | 0.3257 | 0.8211 |
| 20 | 0.1995 | 0.4086 | 0.2526 | 0.3515 | 0.9174 |
