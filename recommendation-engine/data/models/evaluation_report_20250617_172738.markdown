# Recommendation System Evaluation Report

Generated on: 2025-06-17 17:27:38

## Evaluation Details

- **Test Users:** 109
- **Total Evaluation Time:** 213.81 seconds
- **Random Seed:** 42
- **Cold Start Runs:** 5


## Model Comparison Summary (k=10)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2596 | 0.2689 | 0.2477 | 0.3267 | 0.8028 | 0.5679 |
| ncf | 0.2202 | 0.2167 | 0.2072 | 0.2332 | 0.6743 | 0.3811 |
| hybrid | 0.2844 | 0.2968 | 0.2711 | 0.3254 | 0.8165 | 0.4902 |

## Cold-Start Performance (Averaged across multiple runs)

| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1334±0.0149 | 0.4427±0.0493 | 0.2049±0.0228 | 0.3231±0.0364 | 0.6196±0.0448 | 5 |
| cold_start_hybrid | 0.1204±0.0162 | 0.3993±0.0537 | 0.1849±0.0249 | 0.2742±0.0401 | 0.5469±0.0693 | 5 |

## Evaluation Times

| Model | Time (seconds) |
|-------|----------------|
| fecf | 12.75 |
| ncf | 11.06 |
| hybrid | 38.39 |
| cold_start_fecf | 44.65 |
| cold_start_hybrid | 104.20 |

## Detailed Metrics by K-Value

### fecf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3284 | 0.1801 | 0.2167 | 0.3489 | 0.6009 |
| 10 | 0.2596 | 0.2689 | 0.2477 | 0.3267 | 0.8028 |
| 20 | 0.2087 | 0.4421 | 0.2680 | 0.3799 | 0.9266 |

### ncf

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.2128 | 0.1083 | 0.1354 | 0.2063 | 0.4266 |
| 10 | 0.2202 | 0.2167 | 0.2072 | 0.2332 | 0.6743 |
| 20 | 0.1913 | 0.3798 | 0.2420 | 0.2955 | 0.8440 |

### hybrid

| K | Precision | Recall | F1 | NDCG | Hit Ratio |
|---|-----------|--------|-----|------|-----------|
| 5 | 0.3064 | 0.1605 | 0.1977 | 0.3034 | 0.5275 |
| 10 | 0.2844 | 0.2968 | 0.2711 | 0.3254 | 0.8165 |
| 20 | 0.1954 | 0.4129 | 0.2510 | 0.3488 | 0.9312 |
