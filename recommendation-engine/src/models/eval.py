"""
Evaluasi model rekomendasi
"""

import os
import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple, Any, Union, Callable
import time
import json
from datetime import datetime
from sklearn.model_selection import train_test_split
from pathlib import Path
import random

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import (
    EVAL_METRICS, 
    EVAL_K_VALUES, 
    EVAL_TEST_RATIO, 
    EVAL_RANDOM_SEED,
    MODELS_DIR
)

# Import model components (for type hints)
from src.models.alt_fecf import FeatureEnhancedCF
from src.models.ncf import NCFRecommender
from src.models.hybrid import HybridRecommender

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


def precision_at_k(actual: List[str], predicted: List[str], k: int) -> float:
    """
    Calculate precision@k
    
    Args:
        actual: List of actual relevant items
        predicted: List of predicted items
        k: Number of top predictions to consider
        
    Returns:
        float: Precision@k
    """
    if len(predicted) == 0 or k <= 0:
        return 0.0
    
    # Use only top-k predictions
    pred_k = predicted[:k]
    
    # Count number of relevant items in top-k predictions
    num_hits = len(set(pred_k) & set(actual))
    
    # Calculate precision
    return num_hits / min(k, len(pred_k))


def recall_at_k(actual: List[str], predicted: List[str], k: int) -> float:
    """
    Calculate recall@k
    
    Args:
        actual: List of actual relevant items
        predicted: List of predicted items
        k: Number of top predictions to consider
        
    Returns:
        float: Recall@k
    """
    if len(actual) == 0 or len(predicted) == 0 or k <= 0:
        return 0.0
    
    # Use only top-k predictions
    pred_k = predicted[:k]
    
    # Count number of relevant items in top-k predictions
    num_hits = len(set(pred_k) & set(actual))
    
    # Calculate recall
    return num_hits / len(actual)


def f1_at_k(actual: List[str], predicted: List[str], k: int) -> float:
    """
    Calculate F1@k
    
    Args:
        actual: List of actual relevant items
        predicted: List of predicted items
        k: Number of top predictions to consider
        
    Returns:
        float: F1@k
    """
    p = precision_at_k(actual, predicted, k)
    r = recall_at_k(actual, predicted, k)
    
    if p + r == 0:
        return 0.0
    
    return 2 * (p * r) / (p + r)


def ndcg_at_k(actual: List[str], predicted: List[str], k: int) -> float:
    """
    Calculate Normalized Discounted Cumulative Gain (NDCG) at k
    
    Args:
        actual: List of actual relevant items
        predicted: List of predicted items
        k: Number of top predictions to consider
        
    Returns:
        float: NDCG@k
    """
    if len(actual) == 0 or len(predicted) == 0 or k <= 0:
        return 0.0
    
    # Use only top-k predictions
    pred_k = predicted[:k]
    
    # Calculate DCG
    dcg = 0.0
    for i, item in enumerate(pred_k):
        if item in actual:
            # Use binary relevance (1 if relevant, 0 if not)
            # Rank position is i+1 (1-indexed)
            dcg += 1.0 / np.log2(i + 2)  # log2(2) = 1, log2(3) = ~1.58, etc.
    
    # Calculate ideal DCG
    idcg = 0.0
    for i in range(min(len(actual), k)):
        idcg += 1.0 / np.log2(i + 2)
    
    if idcg == 0:
        return 0.0
    
    return dcg / idcg


def mean_average_precision(actual_lists: List[List[str]], predicted_lists: List[List[str]], k: int) -> float:
    """
    Calculate Mean Average Precision (MAP) at k
    
    Args:
        actual_lists: List of lists of actual relevant items
        predicted_lists: List of lists of predicted items
        k: Number of top predictions to consider
        
    Returns:
        float: MAP@k
    """
    if not actual_lists or not predicted_lists:
        return 0.0
    
    # Calculate average precision for each user
    aps = []
    for actual, predicted in zip(actual_lists, predicted_lists):
        if not actual:
            continue
            
        # Use only top-k predictions
        pred_k = predicted[:k]
        
        # Calculate AP
        hits = 0
        sum_prec = 0.0
        
        for i, item in enumerate(pred_k):
            if item in actual:
                hits += 1
                sum_prec += hits / (i + 1)
        
        if hits > 0:
            aps.append(sum_prec / len(actual))
    
    if not aps:
        return 0.0
    
    return sum(aps) / len(aps)


def reciprocal_rank(actual: List[str], predicted: List[str]) -> float:
    """
    Calculate Reciprocal Rank
    
    Args:
        actual: List of actual relevant items
        predicted: List of predicted items
        
    Returns:
        float: Reciprocal Rank
    """
    if not actual or not predicted:
        return 0.0
    
    # Find the rank of the first relevant item
    for i, item in enumerate(predicted):
        if item in actual:
            return 1.0 / (i + 1)
    
    return 0.0


def mean_reciprocal_rank(actual_lists: List[List[str]], predicted_lists: List[List[str]]) -> float:
    """
    Calculate Mean Reciprocal Rank (MRR)
    
    Args:
        actual_lists: List of lists of actual relevant items
        predicted_lists: List of lists of predicted items
        
    Returns:
        float: MRR
    """
    if not actual_lists or not predicted_lists:
        return 0.0
    
    # Calculate reciprocal rank for each user
    rrs = []
    for actual, predicted in zip(actual_lists, predicted_lists):
        if not actual:
            continue
            
        rrs.append(reciprocal_rank(actual, predicted))
    
    if not rrs:
        return 0.0
    
    return sum(rrs) / len(rrs)


def hit_ratio(actual_lists: List[List[str]], predicted_lists: List[List[str]], k: int) -> float:
    """
    Calculate Hit Ratio at k
    
    Args:
        actual_lists: List of lists of actual relevant items
        predicted_lists: List of lists of predicted items
        k: Number of top predictions to consider
        
    Returns:
        float: Hit Ratio@k
    """
    if not actual_lists or not predicted_lists:
        return 0.0
    
    # Calculate hits for each user
    hits = 0
    for actual, predicted in zip(actual_lists, predicted_lists):
        if not actual:
            continue
            
        # Use only top-k predictions
        pred_k = predicted[:k]
        
        # Check if there is at least one hit
        if any(item in actual for item in pred_k):
            hits += 1
    
    return hits / len(actual_lists)


def evaluate_model(model_name: str, 
                  recommender: Any, 
                  test_users: List[str],
                  test_interactions: Dict[str, List[str]],
                  k_values: List[int] = [5, 10, 20],
                  metrics: List[str] = ['precision', 'recall', 'ndcg', 'map', 'mrr', 'hit_ratio'],
                  debug: bool = True) -> Dict[str, Any]:
    """
    Evaluate a recommender model with improved debugging
    
    Args:
        model_name: Name of the model
        recommender: Recommender model
        test_users: List of test users
        test_interactions: Dictionary mapping users to their test interactions
        k_values: List of k values for evaluation
        metrics: List of metrics to compute
        debug: Whether to print debug information
        
    Returns:
        dict: Evaluation results
    """
    logger.info(f"Evaluating {model_name} model")
    start_time = time.time()
    
    # Store results
    results = {
        'model': model_name,
        'num_users': len(test_users),
        'timestamp': datetime.now().isoformat()
    }
    
    # Store predictions for all users
    all_actual = []
    all_predicted = []
    
    # DEBUG: Detailed logging
    total_recommendations = 0
    total_hits = 0
    empty_recommendations = 0
    
    # Randomly choose a few users for detailed debugging
    debug_users = random.sample(test_users, min(5, len(test_users))) if debug else []
    
    # Generate recommendations for each test user
    for user_id in test_users:
        # Get actual test interactions
        actual_items = test_interactions.get(user_id, [])
        
        if not actual_items:
            continue
        
        # DEBUG: Log sample users
        is_debug_user = user_id in debug_users
        if is_debug_user:
            logger.info(f"DEBUG - Generating recommendations for user {user_id}")
            logger.info(f"DEBUG - User has {len(actual_items)} test interactions")
            logger.info(f"DEBUG - Sample test items: {actual_items[:3]}")
        
        # Get recommendations (project IDs only)
        try:
            # Use model-specific recommendation method
            if hasattr(recommender, 'recommend_for_user'):
                recommendations = recommender.recommend_for_user(user_id, n=max(k_values), exclude_known=False)
                predicted_items = [item_id for item_id, _ in recommendations]
            else:
                # Fallback for models without recommend_for_user
                recommendations = recommender.recommend_projects(user_id, n=max(k_values))
                predicted_items = [item.get('id') for item in recommendations]
                
            # DEBUG: Log recommendations
            if is_debug_user:
                logger.info(f"DEBUG - Received {len(predicted_items)} recommendations")
                logger.info(f"DEBUG - Sample recommendations: {predicted_items[:5]}")
                
            # Count empty recommendations
            if not predicted_items:
                empty_recommendations += 1
                if is_debug_user:
                    logger.warning(f"DEBUG - No recommendations generated for user {user_id}")
            
            # Calculate hits (recommendations that match test items)
            hits = set(predicted_items) & set(actual_items)
            total_recommendations += len(predicted_items)
            total_hits += len(hits)
            
            if is_debug_user:
                logger.info(f"DEBUG - Found {len(hits)} hits out of {len(predicted_items)} recommendations")
                if hits:
                    logger.info(f"DEBUG - Hits: {list(hits)}")
                    
        except Exception as e:
            logger.error(f"Error generating recommendations for user {user_id}: {str(e)}")
            import traceback
            logger.error(traceback.format_exc())
            predicted_items = []
        
        # Store for later use
        all_actual.append(actual_items)
        all_predicted.append(predicted_items)
    
    # DEBUG: Overall statistics
    if debug:
        logger.info(f"DEBUG - Summary for {model_name}:")
        logger.info(f"DEBUG - Total test users: {len(test_users)}")
        logger.info(f"DEBUG - Users with empty recommendations: {empty_recommendations}")
        logger.info(f"DEBUG - Total recommendations: {total_recommendations}")
        logger.info(f"DEBUG - Total hits: {total_hits}")
        if total_recommendations > 0:
            logger.info(f"DEBUG - Overall hit rate: {total_hits/total_recommendations:.4f}")
    
    # Calculate metrics as before...
    for k in k_values:
        # Calculate precision, recall, and F1 for each user
        precision_sum = 0
        recall_sum = 0
        f1_sum = 0
        ndcg_sum = 0
        
        for actual, predicted in zip(all_actual, all_predicted):
            precision_sum += precision_at_k(actual, predicted, k)
            recall_sum += recall_at_k(actual, predicted, k)
            f1_sum += f1_at_k(actual, predicted, k)
            ndcg_sum += ndcg_at_k(actual, predicted, k)
        
        # Calculate mean metrics
        num_users = len(all_actual)
        mean_precision = precision_sum / num_users if num_users > 0 else 0
        mean_recall = recall_sum / num_users if num_users > 0 else 0
        mean_f1 = f1_sum / num_users if num_users > 0 else 0
        mean_ndcg = ndcg_sum / num_users if num_users > 0 else 0
        
        # Calculate MAP
        map_score = mean_average_precision(all_actual, all_predicted, k)
        
        # Calculate Hit Ratio
        hr_score = hit_ratio(all_actual, all_predicted, k)
        
        # Store results
        results[f'precision@{k}'] = mean_precision
        results[f'recall@{k}'] = mean_recall
        results[f'f1@{k}'] = mean_f1
        results[f'ndcg@{k}'] = mean_ndcg
        results[f'map@{k}'] = map_score
        results[f'hit_ratio@{k}'] = hr_score
    
    # Calculate MRR (not k-dependent)
    mrr_score = mean_reciprocal_rank(all_actual, all_predicted)
    results['mrr'] = mrr_score
    
    # Add summary metrics (using k=10 as default)
    results['precision'] = results.get('precision@10', 0)
    results['recall'] = results.get('recall@10', 0)
    results['f1'] = results.get('f1@10', 0)
    results['ndcg'] = results.get('ndcg@10', 0)
    results['map'] = results.get('map@10', 0)
    results['hit_ratio'] = results.get('hit_ratio@10', 0)
    
    # Calculate evaluation time
    eval_time = time.time() - start_time
    results['evaluation_time'] = eval_time
    
    logger.info(f"Evaluation of {model_name} completed in {eval_time:.2f}s")
    logger.info(f"Results: Precision@10={results['precision']:.4f}, "
               f"Recall@10={results['recall']:.4f}, "
               f"NDCG@10={results['ndcg']:.4f}, "
               f"Hit Ratio@10={results['hit_ratio']:.4f}")
    
    return results


def prepare_test_data(user_item_matrix: pd.DataFrame, 
                    test_ratio: float = 0.2, 
                    min_interactions: int = 5,
                    random_seed: int = 42) -> Tuple[List[str], Dict[str, List[str]]]:
    """
    Prepare test data for model evaluation
    
    Args:
        user_item_matrix: User-item interaction matrix
        test_ratio: Proportion of interactions to use for testing
        min_interactions: Minimum number of interactions required for a user
        random_seed: Random seed for reproducibility
        
    Returns:
        tuple: (test_users, test_interactions)
    """
    # Filter users with minimum number of interactions
    user_interactions = {}
    test_interactions = {}
    test_users = []
    
    for user_id in user_item_matrix.index:
        # Get positive interactions
        user_items = user_item_matrix.loc[user_id]
        positive_items = user_items[user_items > 0].index.tolist()
        
        if len(positive_items) >= min_interactions:
            user_interactions[user_id] = positive_items
    
    # Split interactions for each user
    for user_id, items in user_interactions.items():
        if len(items) < min_interactions:
            continue
            
        # Split into train and test
        rng = np.random.default_rng(random_seed + hash(user_id) % 10000)  # Create RNG instance

        if len(items) > min_interactions * 2:  # Ensure enough items for both train and test
            test_size = max(int(len(items) * test_ratio), 2)  # At least 2 test items
            test_items = rng.choice(items, size=test_size, replace=False).tolist()
            
            test_interactions[user_id] = test_items
            test_users.append(user_id)
    
    logger.info(f"Prepared test data with {len(test_users)} users "
                f"and {sum(len(items) for items in test_interactions.values())} test interactions")
    
    return test_users, test_interactions


def evaluate_all_models(models: Dict[str, Any], 
                       user_item_matrix: pd.DataFrame,
                       test_ratio: float = EVAL_TEST_RATIO,
                       min_interactions: int = 5,
                       k_values: List[int] = EVAL_K_VALUES,
                       save_results: bool = True) -> Dict[str, Dict[str, Any]]:
    """
    Evaluate multiple recommender models
    """
    # Prepare test data
    test_users, test_interactions = prepare_test_data(
        user_item_matrix, 
        test_ratio=test_ratio,
        min_interactions=min_interactions,
        random_seed=EVAL_RANDOM_SEED
    )
    
    logger.info(f"Prepared test data with {len(test_users)} users and {sum(len(items) for items in test_interactions.values())} test interactions")
    
    # PERBAIKAN: Eksplisit muat model sebelum evaluasi
    for model_name, model in models.items():
        # Periksa apakah model perlu dimuat
        if hasattr(model, 'model') and model.model is None:
            logger.info(f"Model {model_name} not loaded, trying to load from saved file...")
            
            # Cari file model berdasarkan jenis model
            if model_name == 'fecf':
                # Cari file FECF model terbaru
                fecf_files = [f for f in os.listdir(MODELS_DIR) 
                            if f.startswith("fecf_model_") and f.endswith(".pkl")]
                if fecf_files:
                    latest_model = sorted(fecf_files)[-1]
                    model_path = os.path.join(MODELS_DIR, latest_model)
                    logger.info(f"Loading {model_name} from {model_path}")
                    model.load_model(model_path)
            
            elif model_name == 'ncf':
                # Coba path model NCF default
                model_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
                if os.path.exists(model_path):
                    logger.info(f"Loading {model_name} from {model_path}")
                    model.load_model(model_path)
            
            elif model_name == 'hybrid':
                # Cari hybrid model terbaru
                hybrid_files = [f for f in os.listdir(MODELS_DIR) 
                            if f.startswith("hybrid_model_") and f.endswith(".pkl")]
                if hybrid_files:
                    latest_model = sorted(hybrid_files)[-1]
                    model_path = os.path.join(MODELS_DIR, latest_model)
                    logger.info(f"Loading {model_name} from {model_path}")
                    model.load_model(model_path)
    
    # Evaluate each model
    results = {}
    for model_name, model in models.items():
        # Verifikasi model sudah dimuat
        if hasattr(model, 'model') and model.model is None:
            logger.error(f"Model {model_name} could not be loaded for evaluation")
            results[model_name] = {
                "precision": 0.0,
                "recall": 0.0,
                "ndcg": 0.0,
                "hit_ratio": 0.0,
                "error": "model_not_loaded"
            }
            continue
            
        model_results = evaluate_model(
            model_name=model_name,
            recommender=model,
            test_users=test_users,
            test_interactions=test_interactions,
            k_values=k_values
        )
        
        results[model_name] = model_results
    
    # Save results if requested
    if save_results:
        save_evaluation_results(results)
    
    return results


def evaluate_cold_start(model: Any,
                       model_name: str,
                       user_item_matrix: pd.DataFrame,
                       cold_start_users: int = 100,  # Meningkatkan dari 20 ke 100
                       test_ratio: float = 0.5,
                       k_values: List[int] = EVAL_K_VALUES) -> Dict[str, Any]:
    """
    Evaluate model performance on cold-start users dengan metodologi yang lebih ketat
    """
    logger.info(f"Evaluating {model_name} on cold-start scenario")
    
    # Verifikasi model sebelum evaluasi
    if hasattr(model, 'model') and model.model is None:
        logger.error(f"Model {model_name} not trained or loaded")
        return {
            "precision": 0.0, 
            "recall": 0.0, 
            "ndcg": 0.0, 
            "hit_ratio": 0.0, 
            "error": "model_not_loaded"
        }
    
    # Find users with sufficient interactions - MENINGKATKAN THRESHOLD
    user_counts = (user_item_matrix > 0).sum(axis=1)
    # Minimal 15 interaksi untuk evaluasi cold-start yang lebih ketat
    eligible_users = user_counts[user_counts >= 15].index.tolist()
    
    if not eligible_users or len(eligible_users) < cold_start_users:
        logger.warning(f"Not enough users for cold-start evaluation. Found {len(eligible_users)} eligible users")
        if len(eligible_users) < 50:  # Minimal 50 pengguna
            return {"error": "insufficient_users"}
        cold_start_users = min(len(eligible_users), cold_start_users)
    
    # Create RNG instance with more varied seed
    seed = int(time.time()) % 10000  # Menggunakan seed dinamis
    rng = np.random.default_rng(seed)

    # Sample users for cold-start simulation dengan stratifikasi
    # Stratifikasi berdasarkan jumlah interaksi untuk menghindari bias
    user_strata = []
    strata_ranges = [(15, 20), (21, 30), (31, 50), (51, 100), (101, float('inf'))]
    
    for low, high in strata_ranges:
        strata_users = user_counts[(user_counts >= low) & (user_counts < high)].index.tolist()
        if strata_users:
            user_strata.append(strata_users)
    
    # Ambil pengguna dari setiap strata secara proporsional
    cold_start_user_ids = []
    users_per_strata = max(cold_start_users // len(user_strata), 1)
    
    for strata in user_strata:
        sample_size = min(users_per_strata, len(strata))
        sampled_users = rng.choice(strata, size=sample_size, replace=False).tolist()
        cold_start_user_ids.extend(sampled_users)
    
    # Tambahkan pengguna acak jika belum cukup
    if len(cold_start_user_ids) < cold_start_users:
        remaining = cold_start_users - len(cold_start_user_ids)
        remaining_users = [u for u in eligible_users if u not in cold_start_user_ids]
        if remaining_users:
            additional_users = rng.choice(remaining_users, size=min(remaining, len(remaining_users)), replace=False).tolist()
            cold_start_user_ids.extend(additional_users)
    
    # Batasi ke jumlah yang diminta
    cold_start_user_ids = cold_start_user_ids[:cold_start_users]
    logger.info(f"Selected {len(cold_start_user_ids)} users for cold-start evaluation")

    # Prepare test interactions dengan lebih banyak validasi
    test_interactions = {}
    for user_id in cold_start_user_ids:
        user_items = user_item_matrix.loc[user_id]
        positive_items = user_items[user_items > 0].index.tolist()
        
        # Memastikan setidaknya 5 item untuk testing
        if len(positive_items) < 10:  # Minimal 10 item total
            logger.debug(f"User {user_id} has too few items ({len(positive_items)}), skipping")
            continue
            
        # Split into visible and test
        test_size = max(min(int(len(positive_items) * test_ratio), len(positive_items) - 5), 5)
        # Pastikan setidaknya 5 item untuk test dan 5 item untuk train
        
        test_items = rng.choice(
            positive_items, 
            size=test_size, 
            replace=False
        ).tolist()
        test_interactions[user_id] = test_items
    
    if len(test_interactions) < 10:
        logger.warning(f"Not enough valid users for cold-start evaluation after filtering: {len(test_interactions)}")
        return {"error": "insufficient_valid_users", "users_found": len(test_interactions)}
    
    # Evaluate model on cold-start users
    cold_start_results = evaluate_model(
        model_name=f"cold_start_{model_name}",
        recommender=model,
        test_users=list(test_interactions.keys()),
        test_interactions=test_interactions,
        k_values=k_values,
        debug=True  # Aktifkan debug untuk analisis lebih mendalam
    )
    
    # Add cold-start specific info
    cold_start_results['scenario'] = 'cold_start'
    cold_start_results['num_cold_start_users'] = len(test_interactions)
    cold_start_results['avg_test_items'] = sum(len(items) for items in test_interactions.values()) / len(test_interactions)
    
    return cold_start_results


def save_evaluation_results(results: Dict[str, Dict[str, Any]], filename: Optional[str] = None) -> str:
    """
    Save evaluation results to file
    
    Args:
        results: Dictionary of evaluation results
        filename: Filename to save results, if None uses timestamp
        
    Returns:
        str: Path where results were saved
    """
    if filename is None:
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"model_performance_{timestamp}.json"
    
    filepath = os.path.join(MODELS_DIR, filename)
    
    # Ensure directory exists
    os.makedirs(os.path.dirname(filepath), exist_ok=True)
    
    # Convert results to JSON-serializable format
    serializable_results = {}
    for model_name, model_results in results.items():
        serializable_model = {}
        for key, value in model_results.items():
            if isinstance(value, (int, float)):
                serializable_model[key] = float(value)
            elif isinstance(value, (datetime, np.datetime64)):
                serializable_model[key] = value.isoformat()
            else:
                serializable_model[key] = value
        
        serializable_results[model_name] = serializable_model
    
    # Save to file
    with open(filepath, 'w') as f:
        json.dump(serializable_results, f, indent=4)
    
    logger.info(f"Evaluation results saved to {filepath}")
    return filepath


def load_evaluation_results(filepath: str) -> Dict[str, Dict[str, Any]]:
    """
    Load evaluation results from file
    
    Args:
        filepath: Path to evaluation results file
        
    Returns:
        dict: Dictionary of evaluation results
    """
    try:
        with open(filepath, 'r') as f:
            results = json.load(f)
        
        logger.info(f"Evaluation results loaded from {filepath}")
        return results
    except Exception as e:
        logger.error(f"Error loading evaluation results: {str(e)}")
        return {}


def generate_evaluation_report(results: Dict[str, Dict[str, Any]], 
                              output_format: str = 'json') -> str:
    """
    Generate evaluation report from results
    
    Args:
        results: Dictionary of evaluation results
        output_format: Output format ('text', 'markdown', 'json')
        
    Returns:
        str: Evaluation report
    """
    if output_format == 'markdown':
        return _generate_markdown_report(results)
    elif output_format == 'json':
        return _generate_json_report(results)
    else:
        return _generate_text_report(results)
    
def _generate_json_report(results: Dict[str, Dict[str, Any]]) -> str:
    """Generate JSON evaluation report"""
    import json
    
    # Prepare report structure
    report_data = {
        "timestamp": datetime.now().isoformat(),
        "models": {},
        "cold_start_performance": None,
    }
    
    # Process each model's results
    for model_name, model_results in results.items():
        if 'cold_start' in model_name:
            # Store cold start performance separately
            continue
            
        # Extract key metrics
        model_metrics = {
            "precision": model_results.get('precision', 0),
            "recall": model_results.get('recall', 0),
            "f1": model_results.get('f1', 0),
            "ndcg": model_results.get('ndcg', 0),
            "hit_ratio": model_results.get('hit_ratio', 0),
            "mrr": model_results.get('mrr', 0),
            "num_users": model_results.get('num_users', 0),
        }
        
        # Add detailed metrics by k
        detailed_metrics = {}
        for k in [5, 10, 20]:
            k_metrics = {
                f"precision@{k}": model_results.get(f'precision@{k}', 0),
                f"recall@{k}": model_results.get(f'recall@{k}', 0),
                f"f1@{k}": model_results.get(f'f1@{k}', 0),
                f"ndcg@{k}": model_results.get(f'ndcg@{k}', 0),
            }
            detailed_metrics[str(k)] = k_metrics
        
        model_metrics["detailed"] = detailed_metrics
        report_data["models"][model_name] = model_metrics
    
    # Add cold start performance if available
    cold_start_models = [m for m in results if 'cold_start' in m]
    if cold_start_models:
        cold_start_data = {}
        for model_name in cold_start_models:
            model_results = results[model_name]
            cold_start_data[model_name] = {
                "precision": model_results.get('precision', 0),
                "recall": model_results.get('recall', 0),
                "f1": model_results.get('f1', 0),
                "ndcg": model_results.get('ndcg', 0),
                "hit_ratio": model_results.get('hit_ratio', 0),
                "num_users": model_results.get('num_users', 0),
            }
        report_data["cold_start_performance"] = cold_start_data
    
    # Convert to JSON string
    return json.dumps(report_data, indent=2)


def _generate_text_report(results: Dict[str, Dict[str, Any]]) -> str:
    """Generate plain text evaluation report"""
    lines = ["Recommendation System Evaluation Report", "=" * 50, ""]
    
    # Summary table
    lines.append("Model Comparison Summary (k=10)")
    lines.append("-" * 80)
    lines.append(f"{'Model':<20} {'Precision':<10} {'Recall':<10} {'F1':<10} {'NDCG':<10} {'Hit Ratio':<10} {'MRR':<10}")
    lines.append("-" * 80)
    
    for model_name, model_results in results.items():
        if 'cold_start' in model_name:
            continue  # Skip cold-start for main table
            
        precision = model_results.get('precision', 0)
        recall = model_results.get('recall', 0)
        f1 = model_results.get('f1', 0)
        ndcg = model_results.get('ndcg', 0)
        hit_ratio = model_results.get('hit_ratio', 0)
        mrr = model_results.get('mrr', 0)
        
        lines.append(f"{model_name:<20} {precision:<10.4f} {recall:<10.4f} {f1:<10.4f} "
                    f"{ndcg:<10.4f} {hit_ratio:<10.4f} {mrr:<10.4f}")
    
    lines.append("")
    
    # Cold-start performance
    cold_start_models = [m for m in results if 'cold_start' in m]
    if cold_start_models:
        lines.append("Cold-Start Performance")
        lines.append("-" * 80)
        lines.append(f"{'Model':<20} {'Precision':<10} {'Recall':<10} {'F1':<10} {'NDCG':<10} {'Hit Ratio':<10}")
        lines.append("-" * 80)
        
        for model_name in cold_start_models:
            model_results = results[model_name]
            precision = model_results.get('precision', 0)
            recall = model_results.get('recall', 0)
            f1 = model_results.get('f1', 0)
            ndcg = model_results.get('ndcg', 0)
            hit_ratio = model_results.get('hit_ratio', 0)
            
            lines.append(f"{model_name:<20} {precision:<10.4f} {recall:<10.4f} {f1:<10.4f} "
                        f"{ndcg:<10.4f} {hit_ratio:<10.4f}")
        
        lines.append("")
    
    # Detailed model metrics
    for model_name, model_results in results.items():
        if 'cold_start' in model_name:
            continue  # Skip detailed metrics for cold-start
            
        lines.append(f"Detailed Metrics for {model_name}")
        lines.append("-" * 80)
        
        # Add metrics for each k
        for k in [5, 10, 20]:
            precision_k = model_results.get(f'precision@{k}', 0)
            recall_k = model_results.get(f'recall@{k}', 0)
            f1_k = model_results.get(f'f1@{k}', 0)
            ndcg_k = model_results.get(f'ndcg@{k}', 0)
            
            lines.append(f"k={k}:")
            lines.append(f"  Precision: {precision_k:.4f}")
            lines.append(f"  Recall: {recall_k:.4f}")
            lines.append(f"  F1: {f1_k:.4f}")
            lines.append(f"  NDCG: {ndcg_k:.4f}")
        
        lines.append(f"MRR: {model_results.get('mrr', 0):.4f}")
        lines.append("")
    
    return "\n".join(lines)


def _generate_markdown_report(results: Dict[str, Dict[str, Any]]) -> str:
    """Generate Markdown evaluation report"""
    lines = ["# Recommendation System Evaluation Report", ""]
    
    # Add timestamp
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    lines.append(f"Generated on: {timestamp}")
    lines.append("")
    
    # Summary table
    lines.append("## Model Comparison Summary (k=10)")
    lines.append("")
    lines.append("| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |")
    lines.append("|-------|-----------|--------|----|----|-----------|-----|")
    
    for model_name, model_results in results.items():
        if 'cold_start' in model_name:
            continue  # Skip cold-start for main table
            
        precision = model_results.get('precision', 0)
        recall = model_results.get('recall', 0)
        f1 = model_results.get('f1', 0)
        ndcg = model_results.get('ndcg', 0)
        hit_ratio = model_results.get('hit_ratio', 0)
        mrr = model_results.get('mrr', 0)
        
        lines.append(f"| {model_name} | {precision:.4f} | {recall:.4f} | {f1:.4f} | "
                    f"{ndcg:.4f} | {hit_ratio:.4f} | {mrr:.4f} |")
    
    lines.append("")
    
    # Cold-start performance
    cold_start_models = [m for m in results if 'cold_start' in m]
    if cold_start_models:
        lines.append("## Cold-Start Performance")
        lines.append("")
        lines.append("| Model | Precision | Recall | F1 | NDCG | Hit Ratio |")
        lines.append("|-------|-----------|--------|----|-------|-----------|")
        
        for model_name in cold_start_models:
            model_results = results[model_name]
            precision = model_results.get('precision', 0)
            recall = model_results.get('recall', 0)
            f1 = model_results.get('f1', 0)
            ndcg = model_results.get('ndcg', 0)
            hit_ratio = model_results.get('hit_ratio', 0)
            
            lines.append(f"| {model_name} | {precision:.4f} | {recall:.4f} | {f1:.4f} | "
                        f"{ndcg:.4f} | {hit_ratio:.4f} |")
        
        lines.append("")
    
    # Detailed model metrics
    lines.append("## Detailed Model Performance")
    
    for model_name, model_results in results.items():
        if 'cold_start' in model_name:
            continue  # Skip detailed metrics for cold-start
            
        lines.append(f"### {model_name}")
        lines.append("")
        
        # Add metrics for each k
        for k in [5, 10, 20]:
            precision_k = model_results.get(f'precision@{k}', 0)
            recall_k = model_results.get(f'recall@{k}', 0)
            f1_k = model_results.get(f'f1@{k}', 0)
            ndcg_k = model_results.get(f'ndcg@{k}', 0)
            
            lines.append(f"#### k={k}")
            lines.append(f"- Precision: {precision_k:.4f}")
            lines.append(f"- Recall: {recall_k:.4f}")
            lines.append(f"- F1: {f1_k:.4f}")
            lines.append(f"- NDCG: {ndcg_k:.4f}")
            lines.append("")
        
        lines.append(f"**MRR**: {model_results.get('mrr', 0):.4f}")
        lines.append("")
    
    return "\n".join(lines)


if __name__ == "__main__":
    # Test with dummy data
    from src.models.alt_fecf import FeatureEnhancedCF
    from src.models.ncf import NCFRecommender
    from src.models.hybrid import HybridRecommender
    
    # Initialize models
    fecf = FeatureEnhancedCF()
    ncf = NCFRecommender()
    hybrid = HybridRecommender()
    
    # Try to load data
    if fecf.load_data() and ncf.load_data() and hybrid.load_data():
        print("Data loaded successfully for evaluation test")
        
        # Create dictionary of models
        models = {
            'fecf': fecf,
            'ncf': ncf, 
            'hybrid': hybrid
        }
        
        # Run evaluation on a small subset for testing
        test_matrix = fecf.user_item_matrix.iloc[:100, :100]
        
        results = evaluate_all_models(
            models=models,
            user_item_matrix=test_matrix,
            test_ratio=0.2,
            min_interactions=3,
            k_values=[5, 10],
            save_results=True
        )
        
        # Generate report
        report = generate_evaluation_report(results, output_format='markdown') # Bisa diganti nanti ke json untuk output_format nya, jika testing sudah beres.
        print(report)
    else:
        print("Failed to load data for evaluation test")