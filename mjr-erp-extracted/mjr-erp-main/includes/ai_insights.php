<?php
/**
 * AI & Advanced Analytics Engine (Local ML Implementation)
 * Provides pattern recognition, anomaly detection, and decision-making insights.
 */

class AIEngine {
    
    /**
     * Customer Segmentation (RFM - Recency, Frequency, Monetary)
     */
    public static function getCustomerSegments($company_id) {
        $sql = "
            SELECT 
                c.name,
                c.id,
                MAX(so.order_date) as last_order_date,
                COUNT(so.id) as frequency,
                SUM(so.total_amount) as monetary
            FROM customers c
            JOIN sales_orders so ON c.id = so.customer_id
            WHERE so.company_id = ? AND so.status != 'cancelled'
            GROUP BY c.id, c.name
        ";
        $customers = db_fetch_all($sql, [$company_id]);
        
        $insights = [];
        $now = time();
        
        foreach ($customers as $c) {
            $days_since_last = round(($now - strtotime($c['last_order_date'])) / (60 * 60 * 24));
            
            if ($days_since_last > 45 && $c['frequency'] >= 3 && $c['monetary'] > 1000) {
                $insights[] = [
                    'type' => 'churn_risk',
                    'icon' => 'fa-user-slash',
                    'color' => 'danger',
                    'title' => 'Churn Risk Detected',
                    'message' => "High-value customer '<b>{$c['name']}</b>' hasn't purchased in {$days_since_last} days. Consider offering a retention discount or personalized outreach."
                ];
            } else if ($days_since_last <= 30 && $c['frequency'] >= 5) {
                $insights[] = [
                    'type' => 'champion',
                    'icon' => 'fa-star',
                    'color' => 'success',
                    'title' => 'Loyal Champion',
                    'message' => "'<b>{$c['name']}</b>' is a highly loyal customer. Consider them for VIP programs or upselling campaigns."
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * Product Association (Market Basket Analysis - Apriori algorithm concepts)
     */
    public static function getProductAssociations($company_id) {
        $sql = "
            SELECT 
                i1.name as product_a,
                i2.name as product_b,
                COUNT(*) as times_bought_together
            FROM sales_order_lines l1
            JOIN sales_order_lines l2 ON l1.order_id = l2.order_id AND l1.item_id < l2.item_id
            JOIN sales_orders so ON l1.order_id = so.id
            JOIN inventory_items i1 ON l1.item_id = i1.id
            JOIN inventory_items i2 ON l2.item_id = i2.id
            WHERE so.company_id = ? AND so.status != 'cancelled'
            GROUP BY l1.item_id, l2.item_id, i1.name, i2.name
            HAVING times_bought_together >= 2
            ORDER BY times_bought_together DESC
            LIMIT 3
        ";
        $pairs = db_fetch_all($sql, [$company_id]);
        
        $insights = [];
        foreach ($pairs as $p) {
            $insights[] = [
                'type' => 'cross_sell',
                'icon' => 'fa-shopping-basket',
                'color' => 'primary',
                'title' => 'Product Affinity',
                'message' => "Customers frequently buy '<b>{$p['product_a']}</b>' and '<b>{$p['product_b']}</b>' together ({$p['times_bought_together']} times). Consider bundling them for a targeted promotion."
            ];
        }
        
        return $insights;
    }

    /**
     * Anomaly Detection (Z-score on daily sales)
     */
    public static function detectSalesAnomalies($company_id) {
        $sql = "
            SELECT 
                DATE(order_date) as order_date, 
                SUM(total_amount) as daily_revenue
            FROM sales_orders
            WHERE company_id = ? AND status != 'cancelled'
            GROUP BY DATE(order_date)
            ORDER BY order_date ASC
            LIMIT 30
        ";
        $daily_sales = db_fetch_all($sql, [$company_id]);
        
        if (count($daily_sales) < 5) return []; 
        
        $revenues = array_column($daily_sales, 'daily_revenue');
        $mean = array_sum($revenues) / count($revenues);
        
        $variance = 0;
        foreach ($revenues as $rev) {
            $variance += pow($rev - $mean, 2);
        }
        $std_dev = sqrt($variance / count($revenues));
        
        if ($std_dev == 0) return [];
        
        $insights = [];
        
        // Check only the most recent 7 days for actionable anomalies
        $recent_days = array_slice($daily_sales, -7); 
        foreach ($recent_days as $day) {
            $z_score = ($day['daily_revenue'] - $mean) / $std_dev;
            
            if ($z_score > 2) {
                $insights[] = [
                    'type' => 'anomaly_positive',
                    'icon' => 'fa-chart-line',
                    'color' => 'info',
                    'title' => 'Sales Spike Detected',
                    'message' => "Unusual sales spike on <b>{$day['order_date']}</b> ($" . number_format($day['daily_revenue'], 2) . "). Investigate marketing success or bulk orders to replicate."
                ];
            } else if ($z_score < -2 && $day['daily_revenue'] > 0) {
                 $insights[] = [
                    'type' => 'anomaly_negative',
                    'icon' => 'fa-exclamation-triangle',
                    'color' => 'warning',
                    'title' => 'Unusual Sales Drop',
                    'message' => "Sales significantly dropped on <b>{$day['order_date']}</b>. Check if an issue occurred with stock, pricing, or operations."
                ];
            }
        }
        return $insights;
    }
    
    /**
     * Inventory Depletion Forecast (Simple Linear Regression / Run-Rate)
     */
    public static function getInventoryDepletionInsights($company_id) {
         // Determine sales velocity for active items
         $sql = "
            SELECT 
                ii.id, ii.name, ii.reorder_level,
                COALESCE(SUM(isl.quantity_on_hand), 0) as current_stock,
                (
                    SELECT SUM(sol.quantity) 
                    FROM sales_order_lines sol 
                    JOIN sales_orders so ON sol.order_id = so.id 
                    WHERE sol.item_id = ii.id AND so.company_id = ? AND so.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ) as sold_last_30_days
            FROM inventory_items ii
            LEFT JOIN inventory_stock_levels isl ON ii.id = isl.item_id
            WHERE ii.is_active = 1
            GROUP BY ii.id, ii.name, ii.reorder_level
            HAVING sold_last_30_days > 0 AND current_stock > 0
         ";
         $items = db_fetch_all($sql, [$company_id]);
         
         $insights = [];
         foreach ($items as $item) {
             $daily_velocity = $item['sold_last_30_days'] / 30;
             if ($daily_velocity <= 0) continue;
             
             $days_until_stockout = floor($item['current_stock'] / $daily_velocity);
             
             if ($days_until_stockout <= 14 && $days_until_stockout > 0) {
                 $insights[] = [
                     'type' => 'inventory_warning',
                     'icon' => 'fa-boxes',
                     'color' => 'danger',
                     'title' => 'Predicted Stockout',
                     'message' => "Product '<b>{$item['name']}</b>' is selling fast ({$item['sold_last_30_days']} units/mo). Based on predictive run-rate, it will stock out in approximately <b>{$days_until_stockout} days</b>. Replenish immediately."
                 ];
             }
         }
         
         return $insights;
    }
    
    /**
     * Generate all insights
     */
    public static function generateDashboardInsights($company_id) {
        $all = [];
        $all = array_merge($all, self::getCustomerSegments($company_id));
        $all = array_merge($all, self::getProductAssociations($company_id));
        $all = array_merge($all, self::detectSalesAnomalies($company_id));
        $all = array_merge($all, self::getInventoryDepletionInsights($company_id));
        
        // Prioritize by assigning a score or just leaving them as is.
        // We can shuffle or limit to 6 top insights.
        usort($all, function($a, $b) {
            $weights = ['danger' => 4, 'warning' => 3, 'success' => 2, 'primary' => 1, 'info' => 1];
            return $weights[$b['color']] <=> $weights[$a['color']];
        });
        
        return array_slice($all, 0, 6);
    }
}
?>
