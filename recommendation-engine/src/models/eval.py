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
import concurrent.futures

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import (
    EVAL_METRICS, 
    EVAL_K_VALUES, 
    EVAL_TEST_RATIO, 
    EVAL_RANDOM_SEED,
    MODELS_DIR,
    COLD_START_EVAL_CONFIG
)

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


# Fungsi-fungsi metrik
def precision_at_k(actual: List[str], predicted: List[str], k: int) -> float:
    """
    Hitung precision@k
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
    Hitung recall@k
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
    Hitung F1-score@k
    """
    p = precision_at_k(actual, predicted, k)
    r = recall_at_k(actual, predicted, k)
    
    if p + r == 0:
        return 0.0
    
    return 2 * (p * r) / (p + r)


def ndcg_at_k(actual: List[str], predicted: List[str], k: int) -> float:
    """
    Hitung NDCG@k dengan penalti yang lebih besar untuk posisi yang lebih rendah
    """
    if len(actual) == 0 or len(predicted) == 0 or k <= 0:
        return 0.0
    
    # Use only top-k predictions
    pred_k = predicted[:k]
    
    # Calculate DCG with improved logarithmic discount
    # Penalti lebih besar untuk posisi yang lebih rendah
    dcg = 0.0
    for i, item in enumerate(pred_k):
        if item in actual:
            # Log base 1.5 memberikan diskonto yang lebih agresif
            # untuk better reflect cryptocurrency domain
            dcg += 1.0 / np.log(i + 2) / np.log(1.5)
    
    # Calculate ideal DCG
    idcg = 0.0
    for i in range(min(len(actual), k)):
        idcg += 1.0 / np.log(i + 2) / np.log(1.5)
    
    if idcg == 0:
        return 0.0
    
    return dcg / idcg


def mean_average_precision(actual_lists: List[List[str]], predicted_lists: List[List[str]], k: int) -> float:
    """
    Hitung MAP@k (Mean Average Precision at k)
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
    Hitung Reciprocal Rank
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
    Hitung MRR (Mean Reciprocal Rank)
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
    Hitung Hit Ratio at k dengan kriteria yang lebih ketat
    """
    if not actual_lists or not predicted_lists:
        return 0.0
    
    # Calculate hits for each user with more nuanced criteria
    hits = 0
    partial_hits = 0  # For items found but at lower ranks
    
    for actual, predicted in zip(actual_lists, predicted_lists):
        if not actual:
            continue
            
        # Use only top-k predictions
        pred_k = predicted[:k]
        
        # Full hit: item found in top k/3 positions
        top_positions = pred_k[:max(1, k//3)]
        if any(item in actual for item in top_positions):
            hits += 1
        # Partial hit: item found in rest of top k
        elif any(item in actual for item in pred_k):
            partial_hits += 0.5  # Count as half a hit
    
    # Calculate hit ratio with full and partial hits
    return (hits + partial_hits) / len(actual_lists)


def evaluate_model(model_name: str, 
                  recommender: Any, 
                  test_users: List[str],
                  test_interactions: Dict[str, List[str]],
                  k_values: List[int] = [5, 10, 20],
                  metrics: List[str] = ['precision', 'recall', 'ndcg', 'map', 'mrr', 'hit_ratio'],
                  debug: bool = False,
                  max_users_per_batch: int = 50,
                  max_debug_users: int = 3,
                  use_parallel: bool = True,
                  num_workers: int = 4) -> Dict[str, Any]:
    """
    Evaluasi model rekomendasi
    """
    logger.info(f"Evaluating {model_name} model")
    
    # Waktu mulai evaluasi (perbaikan untuk waktu evaluasi yang selalu 0.00)
    start_time = time.perf_counter()
    
    # OPTIMIZATION: Limit debug users
    debug_users = random.sample(test_users, min(max_debug_users, len(test_users))) if debug else []
    
    # Store results
    results = {
        'model': model_name,
        'num_users': len(test_users),
        'timestamp': datetime.now().isoformat()
    }
    
    # OPTIMIZATION: Pre-filter valid test users
    valid_test_users = [user_id for user_id in test_users if user_id in test_interactions]
    if len(valid_test_users) != len(test_users):
        logger.warning(f"Skipping {len(test_users) - len(valid_test_users)} users with no test interactions")
    
    # OPTIMIZATION: Create batches of users
    user_batches = []
    for i in range(0, len(valid_test_users), max_users_per_batch):
        user_batches.append(valid_test_users[i:i+max_users_per_batch])
    
    # Define function to process one user
    def process_user(user_id):
        start_process_time = time.perf_counter()
        
        # Get actual test interactions
        actual_items = test_interactions.get(user_id, [])
        
        if not actual_items:
            return None, None, 0.0
        
        # DEBUG: Log sample users
        is_debug_user = user_id in debug_users
        if is_debug_user:
            logger.info(f"DEBUG - Generating recommendations for user {user_id}")
            logger.info(f"DEBUG - User has {len(actual_items)} test interactions")
        
        # Get recommendations (project IDs only)
        try:
            # Optimize with caching for repeated calls to the same user
            max_k = max(k_values)
            # Use model-specific recommendation method
            if hasattr(recommender, 'recommend_for_user'):
                recommendations = recommender.recommend_for_user(user_id, n=max_k, exclude_known=False)
                predicted_items = [item_id for item_id, _ in recommendations]
            else:
                # Fallback for models without recommend_for_user
                recommendations = recommender.recommend_projects(user_id, n=max_k)
                predicted_items = [item.get('id') for item in recommendations]
                
            process_time = time.perf_counter() - start_process_time
            return actual_items, predicted_items, process_time
            
        except Exception as e:
            logger.error(f"Error generating recommendations for user {user_id}: {str(e)}")
            process_time = time.perf_counter() - start_process_time
            return actual_items, [], process_time
    
    # OPTIMIZATION: Process all users either in parallel or sequentially
    all_actual = []
    all_predicted = []
    total_processing_time = 0.0
    
    if use_parallel and num_workers > 1:
        logger.info(f"Processing {len(user_batches)} batches with {num_workers} parallel workers")
        batch_processing_times = []
        
        for batch_idx, user_batch in enumerate(user_batches):
            batch_start_time = time.perf_counter()
            logger.info(f"Processing batch {batch_idx+1}/{len(user_batches)} with {len(user_batch)} users")
            batch_results = []
            
            # Process batch in parallel
            with concurrent.futures.ThreadPoolExecutor(max_workers=num_workers) as executor:
                batch_results = list(executor.map(process_user, user_batch))
            
            # Process batch results
            batch_proc_time = 0.0
            for actual_items, predicted_items, proc_time in batch_results:
                if actual_items is None:
                    continue
                all_actual.append(actual_items)
                all_predicted.append(predicted_items)
                batch_proc_time += proc_time
            
            batch_time = time.perf_counter() - batch_start_time
            batch_processing_times.append(batch_time)
            total_processing_time += batch_proc_time
            logger.info(f"Batch {batch_idx+1} processed in {batch_time:.2f}s, user processing time: {batch_proc_time:.2f}s")
    else:
        # Sequential processing
        for batch_idx, user_batch in enumerate(user_batches):
            batch_start_time = time.perf_counter()
            logger.info(f"Processing batch {batch_idx+1}/{len(user_batches)} with {len(user_batch)} users")
            
            # OPTIMIZATION: Pre-allocate batch results
            batch_actual = []
            batch_predicted = []
            batch_proc_time = 0.0
            
            for user_id in user_batch:
                actual_items, predicted_items, proc_time = process_user(user_id)
                if actual_items is None:
                    continue
                batch_actual.append(actual_items)
                batch_predicted.append(predicted_items)
                batch_proc_time += proc_time
            
            # Add batch results to overall results
            all_actual.extend(batch_actual)
            all_predicted.extend(batch_predicted)
            total_processing_time += batch_proc_time
            
            batch_time = time.perf_counter() - batch_start_time
            logger.info(f"Batch {batch_idx+1} processed in {batch_time:.2f}s, user processing time: {batch_proc_time:.2f}s")
    
    # Pastikan ada hasil yang bisa dievaluasi
    if not all_actual or not all_predicted:
        logger.error("No valid evaluation results - all users may have failed")
        return {
            "error": "No valid evaluation results",
            "model": model_name,
            "evaluation_time": time.perf_counter() - start_time
        }
        
    # OPTIMIZATION: Vectorized metrics calculation
    metrics_values = {}
    
    # Calculate all metrics at once for all k values
    for k in k_values:
        precision_values = []
        recall_values = []
        f1_values = []
        ndcg_values = []
        
        for actual, predicted in zip(all_actual, all_predicted):
            precision_values.append(precision_at_k(actual, predicted, k))
            recall_values.append(recall_at_k(actual, predicted, k))
            f1_values.append(f1_at_k(actual, predicted, k))
            ndcg_values.append(ndcg_at_k(actual, predicted, k))
        
        # Use numpy for faster calculation
        metrics_values[f'precision@{k}'] = np.mean(precision_values) if precision_values else 0
        metrics_values[f'recall@{k}'] = np.mean(recall_values) if recall_values else 0
        metrics_values[f'f1@{k}'] = np.mean(f1_values) if f1_values else 0
        metrics_values[f'ndcg@{k}'] = np.mean(ndcg_values) if ndcg_values else 0
        metrics_values[f'map@{k}'] = mean_average_precision(all_actual, all_predicted, k)
        metrics_values[f'hit_ratio@{k}'] = hit_ratio(all_actual, all_predicted, k)
    
    # Calculate MRR (not k-dependent)
    metrics_values['mrr'] = mean_reciprocal_rank(all_actual, all_predicted)
    
    # Add all metrics to results
    for metric, value in metrics_values.items():
        results[metric] = value
    
    # Add summary metrics (using k=10 as default)
    results['precision'] = results.get('precision@10', 0)
    results['recall'] = results.get('recall@10', 0)
    results['f1'] = results.get('f1@10', 0)
    results['ndcg'] = results.get('ndcg@10', 0)
    results['map'] = results.get('map@10', 0)
    results['hit_ratio'] = results.get('hit_ratio@10', 0)
    
    # Calculate evaluation time (perbaikan untuk reporting waktu)
    eval_end_time = time.perf_counter()
    eval_time = eval_end_time - start_time
    results['evaluation_time'] = eval_time
    results['processing_time'] = total_processing_time
    
    logger.info(f"Evaluation of {model_name} completed in {eval_time:.2f}s (processing: {total_processing_time:.2f}s)")
    logger.info(f"Results: Precision@10={results['precision']:.4f}, "
               f"Recall@10={results['recall']:.4f}, "
               f"NDCG@10={results['ndcg']:.4f}, "
               f"Hit Ratio@10={results['hit_ratio']:.4f}")
    
    return results

def prepare_test_data(user_item_matrix: pd.DataFrame, 
                     interactions_df: pd.DataFrame,
                     test_ratio: float = EVAL_TEST_RATIO, 
                     min_interactions: int = 5,
                     random_seed: int = EVAL_RANDOM_SEED,
                     max_test_users: int = 100,
                     temporal_split: bool = True) -> Tuple[List[str], Dict[str, List[str]]]:
    """
    Menyiapkan data test dengan strategi temporal split yang lebih realistis
    """
    logger.info(f"Preparing test data with test_ratio={test_ratio}, "
               f"min_interactions={min_interactions}, random_seed={random_seed}, "
               f"temporal_split={temporal_split}")
    
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
    
    # IMPROVED: Stratified sampling by interaction count with fixed seed
    # Group users by interaction count ranges
    interaction_ranges = [(min_interactions, 10), (11, 20), (21, 50), (51, 100), (101, float('inf'))]
    stratified_users = {r: [] for r in interaction_ranges}
    
    for user_id, items in user_interactions.items():
        n_interactions = len(items)
        for low, high in interaction_ranges:
            if low <= n_interactions <= high:
                stratified_users[(low, high)].append(user_id)
                break
    
    # Sample from each stratum proportionally with consistent seed
    sampled_users = []
    total_eligible = sum(len(users) for users in stratified_users.values())
    
    if total_eligible == 0:
        logger.warning("No eligible users found for testing")
        return [], {}
    
    # OPTIMIZATION: Limit total test users with minimum representatives
    target_test_users = min(int(total_eligible * 0.3), max_test_users)
    logger.info(f"Target test users: {target_test_users} from {total_eligible} eligible")
    
    # Fixed seed RNG for each range to ensure consistency
    base_rng = np.random.RandomState(random_seed)
    
    for (low, high), users in stratified_users.items():
        if not users:
            continue
            
        # Calculate proportion based on stratum size but with higher weights for lower interaction counts
        # This ensures better representation of casual users
        if low <= 20:  # Boost representation of users with fewer interactions
            boost_factor = 1.5
        else:
            boost_factor = 1.0
            
        stratum_ratio = len(users) / total_eligible * boost_factor
        target_count = max(3, int(target_test_users * stratum_ratio))
        
        # Create a predictable seed for this range
        range_seed = random_seed + hash(str(low) + str(high)) % 10000
        range_rng = np.random.RandomState(range_seed)
        
        # Sample from this stratum
        sample_size = min(target_count, len(users))
        sampled = range_rng.choice(users, size=sample_size, replace=False).tolist()
        sampled_users.extend(sampled)
        logger.info(f"Sampled {len(sampled)} users from range {low}-{high}")
    
    # Use temporal split if requested
    if temporal_split and 'timestamp' in interactions_df.columns:
        logger.info("Using temporal split for test data")
        
        # Convert timestamp to datetime if it's a string
        if interactions_df['timestamp'].dtype == 'object':
            interactions_df['timestamp'] = pd.to_datetime(interactions_df['timestamp'])
        
        # For each sampled user, split interactions temporally
        for user_id in sampled_users:
            # Get user's interactions in chronological order
            user_inters = interactions_df[interactions_df['user_id'] == user_id].sort_values('timestamp')
            
            if len(user_inters) < min_interactions:
                continue
                
            # IMPROVEMENT: Use a more realistic temporal split
            # Instead of a fixed percentage split, use a time-based split
            # to better simulate real-world evaluation scenarios
            
            # Get timestamp range
            earliest_time = user_inters['timestamp'].min()
            latest_time = user_inters['timestamp'].max()
            
            # Calculate split point at 70% of the time range
            time_range = latest_time - earliest_time
            split_time = earliest_time + time_range * 0.7
            
            # Use interactions after split_time for testing
            test_interactions_df = user_inters[user_inters['timestamp'] > split_time]
            
            # Ensure at least 2 test interactions
            if len(test_interactions_df) < 2:
                # Fall back to percentage-based split
                split_idx = int(len(user_inters) * (1 - test_ratio))
                split_idx = min(split_idx, len(user_inters) - 2)  # Ensure at least 2 test interactions
                test_interactions_df = user_inters.iloc[split_idx:]
            
            # Extract test items
            test_items = test_interactions_df['project_id'].tolist()
            
            if test_items:
                test_interactions[user_id] = test_items
                test_users.append(user_id)
    else:
        # Use random split if temporal split not requested or timestamp not available
        logger.info("Using random split for test data")
        
        # Split interactions for each user with consistent seed
        for user_id in sampled_users:
            items = user_interactions[user_id]
            
            # Determine test size based on interaction count
            n_items = len(items)
            test_ratio_adjusted = test_ratio
            
            if n_items < 10:
                test_ratio_adjusted = min(0.4, max(0.2, test_ratio))  # 20-40% for few interactions
            
            # Split into train and test with user-specific but consistent seed
            user_seed = random_seed + hash(user_id) % 10000
            user_rng = np.random.RandomState(user_seed)
            
            test_size = max(int(n_items * test_ratio_adjusted), 2)  # At least 2 test items
            test_size = min(test_size, n_items - 1)  # Leave at least 1 for training
            
            # Predictable shuffle
            shuffled_items = items.copy()
            user_rng.shuffle(shuffled_items)
            test_items = shuffled_items[:test_size]
            
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
                       save_results: bool = True,
                       max_test_users: int = 100,
                       max_users_per_batch: int = 50,
                       use_parallel: bool = True,
                       num_workers: int = 4,
                       eval_cold_start: bool = True,
                       cold_start_runs: int = 5,
                       regular_runs: int = 1) -> Dict[str, Dict[str, Any]]:
    """
    Perbaikan untuk mengevaluasi semua model dengan multiple runs untuk hasil yang lebih robust
    """
    # Main evaluation start time
    evaluation_start_time = time.perf_counter()
    
    interactions_df = None

    for model_name, model in models.items():
        if hasattr(model, 'interactions_df') and model.interactions_df is not None:
            interactions_df = model.interactions_df
            break

    if interactions_df is None:
        logger.error("No interactions data found in any model")
        return {"error": "No interactions data found"}

    test_users, test_interactions = prepare_test_data(
        user_item_matrix, 
        interactions_df,
        test_ratio=test_ratio,
        min_interactions=min_interactions,
        random_seed=EVAL_RANDOM_SEED,
        max_test_users=max_test_users
    )
    
    logger.info(f"Prepared test data with {len(test_users)} users and {sum(len(items) for items in test_interactions.values())} test interactions")
    
    # Ensure models are loaded
    for model_name, model in models.items():
        if hasattr(model, 'model') and model.model is None:
            logger.info(f"Model {model_name} not loaded, trying to load from saved file...")
            
            if model_name == 'fecf':
                fecf_files = [f for f in os.listdir(MODELS_DIR) 
                            if f.startswith("fecf_model_") and f.endswith(".pkl")]
                if fecf_files:
                    latest_model = sorted(fecf_files)[-1]
                    model_path = os.path.join(MODELS_DIR, latest_model)
                    logger.info(f"Loading {model_name} from {model_path}")
                    model.load_model(model_path)
            
            elif model_name == 'ncf':
                model_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
                if os.path.exists(model_path):
                    logger.info(f"Loading {model_name} from {model_path}")
                    model.load_model(model_path)
            
            elif model_name == 'hybrid':
                hybrid_files = [f for f in os.listdir(MODELS_DIR) 
                            if f.startswith("hybrid_model_") and f.endswith(".pkl")]
                if hybrid_files:
                    latest_model = sorted(hybrid_files)[-1]
                    model_path = os.path.join(MODELS_DIR, latest_model)
                    logger.info(f"Loading {model_name} from {model_path}")
                    model.load_model(model_path)
    
    # Regular evaluation with multiple runs for more robust results
    # IMPROVED: Use multiple runs like cold-start for regular models too
    num_runs = max(1, regular_runs)  # Default to at least 1 run
    logger.info(f"Performing {num_runs} evaluation runs for regular models")
    
    all_results = {model_name: [] for model_name in models.keys()}
    
    # Run multiple evaluations for each model with different seeds
    for run in range(num_runs):
        run_seed = EVAL_RANDOM_SEED + run * 997  # Use different seeds for each run
        logger.info(f"Starting evaluation run {run+1}/{num_runs} with seed {run_seed}")
        
        # Evaluate each model
        for model_name, model in models.items():
            if hasattr(model, 'model') and model.model is None:
                logger.error(f"Model {model_name} could not be loaded for evaluation run {run+1}")
                all_results[model_name].append({
                    "precision": 0.0,
                    "recall": 0.0,
                    "ndcg": 0.0,
                    "hit_ratio": 0.0,
                    "error": "model_not_loaded"
                })
                continue
                    
            # Add a better timeout
            try:
                # Set run-specific seed for reproducible but different evaluations
                np.random.seed(run_seed)
                random.seed(run_seed)
                
                model_results = evaluate_model(
                    model_name=f"{model_name}_run{run+1}",
                    recommender=model,
                    test_users=test_users,
                    test_interactions=test_interactions,
                    k_values=k_values,
                    max_users_per_batch=max_users_per_batch,
                    use_parallel=use_parallel,
                    num_workers=num_workers
                )
                
                all_results[model_name].append(model_results)
                
            except Exception as e:
                logger.error(f"Error evaluating model {model_name} in run {run+1}: {e}")
                import traceback
                logger.error(traceback.format_exc())
                
                all_results[model_name].append({
                    "error": str(e),
                    "model": model_name,
                    "evaluation_time": 0.0
                })
    
    # Format results for multiple runs - compute mean and std
    aggregated_results = {}
    
    for model_name, run_results in all_results.items():
        if not run_results:
            continue
            
        # Filter out runs with errors
        valid_runs = [r for r in run_results if "error" not in r]
        num_valid_runs = len(valid_runs)
            
        if num_valid_runs == 0:
            # All runs failed, use first run result
            aggregated_results[model_name] = run_results[0]
            continue
            
        # Create base result from first valid run
        aggregated_result = valid_runs[0].copy()
        aggregated_result['num_runs'] = num_valid_runs
            
        # Get list of metrics first before modifying dictionary
        metrics_to_process = [metric for metric in list(aggregated_result.keys()) 
                            if isinstance(aggregated_result[metric], (int, float)) and 
                            metric not in ['num_runs', 'evaluation_time', 'model']]

        # Now process each metric separately
        for metric in metrics_to_process:
            # Collect values across all runs
            values = [r.get(metric, 0) for r in valid_runs]
            
            # Calculate mean and std
            mean_value = np.mean(values)
            std_value = np.std(values)
            
            # Store mean and std
            aggregated_result[metric] = mean_value
            aggregated_result[f'{metric}_std'] = std_value
            
        # Sum evaluation times
        total_time = sum(r.get('evaluation_time', 0) for r in valid_runs)
        aggregated_result['evaluation_time'] = total_time
            
        # Store aggregated result
        aggregated_results[model_name] = aggregated_result

    # Evaluate cold-start if requested
    if eval_cold_start:
        logger.info(f"Evaluating cold-start scenarios with {cold_start_runs} runs...")
        
        # Ensure we use multiple runs for cold-start due to randomness
        # Use FECF and Hybrid for cold-start evaluation
        if 'fecf' in models:
            logger.info("Evaluating cold-start for FECF...")
            aggregated_results['cold_start_fecf'] = evaluate_cold_start(
                models['fecf'],
                model_name="fecf",
                user_item_matrix=user_item_matrix,
                n_runs=cold_start_runs
            )
        
        if 'hybrid' in models:
            logger.info("Evaluating cold-start for Hybrid...")
            aggregated_results['cold_start_hybrid'] = evaluate_cold_start(
                models['hybrid'],
                model_name="hybrid",
                user_item_matrix=user_item_matrix,
                n_runs=cold_start_runs
            )
    
    # Add total evaluation time
    aggregated_results['_metadata'] = {
        'total_evaluation_time': time.perf_counter() - evaluation_start_time,
        'timestamp': datetime.now().isoformat(),
        'num_test_users': len(test_users),
        'test_ratio': test_ratio,
        'min_interactions': min_interactions,
        'random_seed': EVAL_RANDOM_SEED,
        'cold_start_runs': cold_start_runs if eval_cold_start else 0,
        'regular_runs': regular_runs
    }
    
    # Save results if requested
    if save_results:
        save_evaluation_results(aggregated_results)
    
    return aggregated_results

def evaluate_cold_start(model: Any,
                       model_name: str,
                       user_item_matrix: pd.DataFrame,
                       cold_start_users: Optional[int] = None,     
                       k_values: List[int] = [5, 10], 
                       debug: bool = False,
                       max_users_per_batch: int = 50,
                       use_parallel: bool = False,
                       n_runs: int = 5) -> Dict[str, Any]:
    """
    Perbaikan evaluasi cold-start untuk konsistensi yang lebih baik
    """
    # Load configuration or use defaults
    config = {}
    if 'COLD_START_EVAL_CONFIG' in globals():
        config = COLD_START_EVAL_CONFIG
    
    # Set parameters from config or use provided values
    cold_start_users = min(100, cold_start_users or config.get('cold_start_users', 100))
    test_ratio = config.get('test_ratio', 0.3)
    popular_exclude_ratio = config.get('max_popular_items_exclude', 0.2)
    min_interactions = config.get('min_interactions_required', 3)
    category_diversity_enabled = config.get('category_diversity_enabled', True)
    
    logger.info(f"Evaluating {model_name} on cold-start scenario with {cold_start_users} users, {n_runs} runs")
    
    # Verify model before evaluation
    if hasattr(model, 'model') and model.model is None:
        logger.error(f"Model {model_name} not trained or loaded")
        return {
            "precision": 0.0, 
            "recall": 0.0, 
            "ndcg": 0.0, 
            "hit_ratio": 0.0, 
            "error": "model_not_loaded"
        }
    
    # IMPROVED: Multiple runs for more stable results
    all_run_results = []
    
    for run in range(n_runs):
        run_start_time = time.perf_counter()
        logger.info(f"Cold-start evaluation run {run+1}/{n_runs}")
        
        # Create a predictable but different seed for each run
        run_seed = EVAL_RANDOM_SEED + run * 1000
        rng = np.random.RandomState(run_seed)
        
        # Identify popular items for this run
        item_popularity = user_item_matrix.sum()
        popular_threshold = item_popularity.quantile(1 - popular_exclude_ratio)
        extremely_popular_items = set(item_popularity[item_popularity > popular_threshold].index)
        
        if debug and run == 0:  # Only log on first run
            logger.info(f"Identified {len(extremely_popular_items)} extremely popular items to exclude")
            logger.info(f"Popularity threshold: {popular_threshold} interactions")
            logger.debug(f"Using seed {run_seed} for run {run+1}")
        
        # Find eligible users with predictable order
        user_counts = (user_item_matrix > 0).sum(axis=1)
        eligible_users = user_counts[user_counts >= min_interactions].index.tolist()
        
        if not eligible_users or len(eligible_users) < min(30, cold_start_users):
            logger.warning(f"Not enough users for cold-start evaluation. Found {len(eligible_users)} eligible users")
            if len(eligible_users) < 30:
                return {"error": "insufficient_users"}
            cold_start_users = min(len(eligible_users), cold_start_users)
        
        # Select users for this run with a consistent approach
        # Sort first for predictability
        eligible_users.sort()
        # Then use predictable RNG for sampling
        cold_start_user_ids = rng.choice(eligible_users, size=min(cold_start_users, len(eligible_users)), replace=False).tolist()
        
        # Prepare test interactions
        test_interactions = {}
        
        for user_id in cold_start_user_ids:
            user_items = user_item_matrix.loc[user_id]
            positive_items = user_items[user_items > 0].index.tolist()
            
            # Create a user-specific RNG for consistent filtering
            user_seed = run_seed + hash(user_id) % 10000
            user_rng = np.random.RandomState(user_seed)
            
            # Filter out popular items with a consistent approach
            non_popular_items = [item for item in positive_items if item not in extremely_popular_items]
            
            # If too few non-popular items, include some popular ones
            if len(non_popular_items) < min(5, len(positive_items) // 2):
                supplement_count = min(5, len(positive_items) - len(non_popular_items))
                popular_user_items = [item for item in positive_items if item in extremely_popular_items]
                
                if popular_user_items and supplement_count > 0:
                    # Consistent randomization
                    indices = user_rng.choice(
                        len(popular_user_items), 
                        size=min(supplement_count, len(popular_user_items)), 
                        replace=False
                    )
                    added_items = [popular_user_items[i] for i in indices]
                    non_popular_items.extend(added_items)
            
            # Skip if insufficient items
            if len(non_popular_items) < 5:
                continue
            
            # Use consistent split
            # First sort for predictability
            sorted_items = sorted(non_popular_items)
            # Then shuffle predictably
            shuffled_items = sorted_items.copy()
            user_rng.shuffle(shuffled_items)
            
            # Take a consistent percentage as test
            test_size = max(min(int(len(shuffled_items) * test_ratio), len(shuffled_items) - 1), 3)
            test_items = shuffled_items[:test_size]
            
            test_interactions[user_id] = test_items
        
        # Evaluate this run
        if len(test_interactions) < 10:
            logger.warning(f"Not enough valid users for cold-start evaluation in run {run+1}: {len(test_interactions)}")
            continue
        
        run_result = evaluate_model(
            model_name=f"cold_start_{model_name}_run{run+1}",
            recommender=model,
            test_users=list(test_interactions.keys()),
            test_interactions=test_interactions,
            k_values=k_values,
            debug=(debug and run == 0),  # Only debug first run
            max_users_per_batch=max_users_per_batch,
            use_parallel=use_parallel
        )
        
        # Add run-specific metadata
        run_result['run_id'] = run + 1
        run_result['run_time'] = time.perf_counter() - run_start_time
        run_result['num_users'] = len(test_interactions)
        run_result['num_test_interactions'] = sum(len(items) for items in test_interactions.values())
        
        all_run_results.append(run_result)
    
    # Aggregate results across runs
    if not all_run_results:
        return {"error": "no_successful_runs"}
    
    aggregated_result = {}
    
    # Metrics to aggregate
    metrics_to_aggregate = ['precision', 'recall', 'f1', 'ndcg', 'hit_ratio']
    for k in k_values:
        metrics_to_aggregate.extend([
            f'precision@{k}', f'recall@{k}', f'f1@{k}', 
            f'ndcg@{k}', f'hit_ratio@{k}'
        ])
    
    # Calculate mean and std for each metric
    for metric in metrics_to_aggregate:
        values = [r.get(metric, 0) for r in all_run_results if metric in r]
        if values:
            aggregated_result[metric] = np.mean(values)
            aggregated_result[f'{metric}_std'] = np.std(values)
    
    # Add metadata
    aggregated_result['scenario'] = 'cold_start'
    aggregated_result['num_runs'] = len(all_run_results)
    aggregated_result['num_cold_start_users'] = cold_start_users
    aggregated_result['test_ratio'] = test_ratio
    aggregated_result['popular_items_excluded'] = popular_exclude_ratio
    
    # Add evaluation time details
    aggregated_result['evaluation_time'] = sum(r.get('evaluation_time', 0) for r in all_run_results)
    aggregated_result['run_times'] = [r.get('run_time', 0) for r in all_run_results]
    
    return aggregated_result


def save_evaluation_results(results: Dict[str, Dict[str, Any]], filename: Optional[str] = None) -> str:
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
            if isinstance(value, (int, float, str, bool, type(None))):
                serializable_model[key] = value
            elif isinstance(value, (datetime, np.datetime64)):
                serializable_model[key] = value.isoformat()
            elif isinstance(value, (np.int64, np.float64)):
                serializable_model[key] = float(value)
            elif isinstance(value, dict):
                # Handle nested dictionaries
                serializable_nested = {}
                for k, v in value.items():
                    if isinstance(v, (np.int64, np.float64)):
                        serializable_nested[k] = float(v)
                    else:
                        serializable_nested[k] = v
                serializable_model[key] = serializable_nested
            else:
                serializable_model[key] = str(value)
        
        serializable_results[model_name] = serializable_model
    
    # Save to file
    with open(filepath, 'w') as f:
        json.dump(serializable_results, f, indent=4)
    
    logger.info(f"Evaluation results saved to {filepath}")
    return filepath


def load_evaluation_results(filepath: str) -> Dict[str, Dict[str, Any]]:
    try:
        with open(filepath, 'r') as f:
            results = json.load(f)
        
        logger.info(f"Evaluation results loaded from {filepath}")
        return results
    except Exception as e:
        logger.error(f"Error loading evaluation results: {str(e)}")
        return {}


def generate_evaluation_report(results: Dict[str, Dict[str, Any]], 
                              output_format: str = 'markdown') -> str:
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
        "cold_start_performance": {},
        "evaluation_metadata": {}
    }
    
    # Extract metadata if available
    if '_metadata' in results:
        report_data["evaluation_metadata"] = results['_metadata']
        results = {k: v for k, v in results.items() if k != '_metadata'}
    
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
            "evaluation_time": model_results.get('evaluation_time', 0),
        }
        
        # Add detailed metrics by k
        detailed_metrics = {}
        for k in [5, 10, 20]:
            k_metrics = {
                f"precision@{k}": model_results.get(f'precision@{k}', 0),
                f"recall@{k}": model_results.get(f'recall@{k}', 0),
                f"f1@{k}": model_results.get(f'f1@{k}', 0),
                f"ndcg@{k}": model_results.get(f'ndcg@{k}', 0),
                f"hit_ratio@{k}": model_results.get(f'hit_ratio@{k}', 0),
            }
            detailed_metrics[str(k)] = k_metrics
        
        model_metrics["detailed"] = detailed_metrics
        
        # Add category performance if available
        if "category_performance" in model_results:
            model_metrics["category_performance"] = model_results["category_performance"]
        
        report_data["models"][model_name] = model_metrics
    
    # Add cold start performance if available
    cold_start_models = [m for m in results if 'cold_start' in m]
    if cold_start_models:
        cold_start_data = {}
        for model_name in cold_start_models:
            model_results = results[model_name]
            
            cold_start_data[model_name] = {
                "precision": model_results.get('precision', 0),
                "precision_std": model_results.get('precision_std', 0),
                "recall": model_results.get('recall', 0),
                "recall_std": model_results.get('recall_std', 0),
                "f1": model_results.get('f1', 0),
                "f1_std": model_results.get('f1_std', 0),
                "ndcg": model_results.get('ndcg', 0),
                "ndcg_std": model_results.get('ndcg_std', 0),
                "hit_ratio": model_results.get('hit_ratio', 0),
                "hit_ratio_std": model_results.get('hit_ratio_std', 0),
                "num_users": model_results.get('num_cold_start_users', 0),
                "num_runs": model_results.get('num_runs', 1),
                "test_ratio": model_results.get('test_ratio', 0),
                "evaluation_time": model_results.get('evaluation_time', 0)
            }
            
        report_data["cold_start_performance"] = cold_start_data
    
    # Convert to JSON string
    return json.dumps(report_data, indent=2)


def _generate_text_report(results: Dict[str, Dict[str, Any]]) -> str:
    """Generate plain text evaluation report"""
    lines = ["Recommendation System Evaluation Report", "=" * 50, ""]
    lines.append(f"Generated on: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    # Add metadata if available
    if '_metadata' in results:
        metadata = results['_metadata']
        lines.append("")
        lines.append("Evaluation Details:")
        lines.append(f"- Test Users: {metadata.get('num_test_users', 'Unknown')}")
        lines.append(f"- Total Evaluation Time: {metadata.get('total_evaluation_time', 0):.2f} seconds")
        lines.append(f"- Random Seed: {metadata.get('random_seed', 'Unknown')}")
        lines.append("")
        
        # Remove metadata from results for summary tables
        results = {k: v for k, v in results.items() if k != '_metadata'}
    
    lines.append("")
    
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
        lines.append(f"{'Model':<20} {'Precision':<14} {'Recall':<14} {'F1':<14} {'NDCG':<14} {'Hit Ratio':<14}")
        lines.append("-" * 80)
        
        for model_name in cold_start_models:
            model_results = results[model_name]
            
            # Format with standard deviation
            precision = f"{model_results.get('precision', 0):.4f}±{model_results.get('precision_std', 0):.4f}"
            recall = f"{model_results.get('recall', 0):.4f}±{model_results.get('recall_std', 0):.4f}"
            f1 = f"{model_results.get('f1', 0):.4f}±{model_results.get('f1_std', 0):.4f}"
            ndcg = f"{model_results.get('ndcg', 0):.4f}±{model_results.get('ndcg_std', 0):.4f}"
            hit_ratio = f"{model_results.get('hit_ratio', 0):.4f}±{model_results.get('hit_ratio_std', 0):.4f}"
            
            lines.append(f"{model_name:<20} {precision:<14} {recall:<14} {f1:<14} "
                        f"{ndcg:<14} {hit_ratio:<14}")
        
        lines.append("")
    
    # Evaluation times information
    lines.append("Evaluation Times")
    lines.append("-" * 80)
    lines.append(f"{'Model':<20} {'Time (seconds)':<15}")
    lines.append("-" * 80)
    
    for model_name, model_results in results.items():
        eval_time = model_results.get('evaluation_time', 0)
        lines.append(f"{model_name:<20} {eval_time:<15.2f}")
    
    lines.append("")
    
    # Detailed model metrics - SIMPLIFIED
    lines.append("Detailed Model Performance (Simplified)")
    lines.append("=" * 80)
    lines.append("")
    
    for model_name, model_results in results.items():
        if 'cold_start' in model_name:
            continue  # Skip detailed metrics for cold-start
            
        lines.append(f"{model_name}")
        lines.append("-" * 80)
        
        # Add metrics for k=10 only
        precision_k = model_results.get('precision@10', 0)
        recall_k = model_results.get('recall@10', 0)
        f1_k = model_results.get('f1@10', 0)
        ndcg_k = model_results.get('ndcg@10', 0)
        
        lines.append(f"Precision@10: {precision_k:.4f}")
        lines.append(f"Recall@10: {recall_k:.4f}")
        lines.append(f"F1@10: {f1_k:.4f}")
        lines.append(f"NDCG@10: {ndcg_k:.4f}")
        lines.append(f"MRR: {model_results.get('mrr', 0):.4f}")
        
        lines.append("")
    
    return "\n".join(lines)


def _generate_markdown_report(results: Dict[str, Dict[str, Any]]) -> str:
    """Generate Markdown evaluation report with standard deviation"""
    lines = ["# Recommendation System Evaluation Report", ""]
    
    # Add timestamp
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    lines.append(f"Generated on: {timestamp}")
    
    # Add metadata if available
    if '_metadata' in results:
        metadata = results['_metadata']
        lines.append("")
        lines.append("## Evaluation Details")
        lines.append("")
        lines.append(f"- **Test Users:** {metadata.get('num_test_users', 'Unknown')}")
        lines.append(f"- **Total Evaluation Time:** {metadata.get('total_evaluation_time', 0):.2f} seconds")
        lines.append(f"- **Random Seed:** {metadata.get('random_seed', 'Unknown')}")
        lines.append(f"- **Cold Start Runs:** {metadata.get('cold_start_runs', 0)}")
        lines.append("")
        
        # Remove metadata from results for summary tables
        results = {k: v for k, v in results.items() if k != '_metadata'}
    
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
    
    # Cold-start performance with standard deviations
    cold_start_models = [m for m in results if 'cold_start' in m]
    if cold_start_models:
        lines.append("## Cold-Start Performance (Averaged across multiple runs)")
        lines.append("")
        lines.append("| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |")
        lines.append("|-------|-----------|--------|----|-------|-----------|------|")
        
        for model_name in cold_start_models:
            model_results = results[model_name]
            precision = model_results.get('precision', 0)
            precision_std = model_results.get('precision_std', 0)
            recall = model_results.get('recall', 0)
            recall_std = model_results.get('recall_std', 0)
            f1 = model_results.get('f1', 0)
            f1_std = model_results.get('f1_std', 0)
            ndcg = model_results.get('ndcg', 0)
            ndcg_std = model_results.get('ndcg_std', 0)
            hit_ratio = model_results.get('hit_ratio', 0)
            hit_ratio_std = model_results.get('hit_ratio_std', 0)
            num_runs = model_results.get('num_runs', 1)
            
            lines.append(f"| {model_name} | {precision:.4f}±{precision_std:.4f} | "
                        f"{recall:.4f}±{recall_std:.4f} | {f1:.4f}±{f1_std:.4f} | "
                        f"{ndcg:.4f}±{ndcg_std:.4f} | {hit_ratio:.4f}±{hit_ratio_std:.4f} | {num_runs} |")
    
    # Evaluation times information
    lines.append("\n## Evaluation Times")
    lines.append("")
    lines.append("| Model | Time (seconds) |")
    lines.append("|-------|----------------|")
    
    for model_name, model_results in results.items():
        eval_time = model_results.get('evaluation_time', 0)
        lines.append(f"| {model_name} | {eval_time:.2f} |")
    
    # Detailed metrics by k-value
    lines.append("\n## Detailed Metrics by K-Value")
    lines.append("")
    
    # Create separate tables for each model
    for model_name, model_results in results.items():
        if 'cold_start' in model_name:
            continue  # Skip cold-start models for detailed metrics
            
        lines.append(f"### {model_name}")
        lines.append("")
        lines.append("| K | Precision | Recall | F1 | NDCG | Hit Ratio |")
        lines.append("|---|-----------|--------|-----|------|-----------|")
        
        for k in [5, 10, 20]:
            precision_k = model_results.get(f'precision@{k}', 0)
            recall_k = model_results.get(f'recall@{k}', 0)
            f1_k = model_results.get(f'f1@{k}', 0)
            ndcg_k = model_results.get(f'ndcg@{k}', 0)
            hit_ratio_k = model_results.get(f'hit_ratio@{k}', 0)
            
            lines.append(f"| {k} | {precision_k:.4f} | {recall_k:.4f} | {f1_k:.4f} | "
                        f"{ndcg_k:.4f} | {hit_ratio_k:.4f} |")
        
        lines.append("")
        
    return "\n".join(lines)


if __name__ == "__main__":
    print("Improved evaluation module loaded - run python main.py evaluate to use it")