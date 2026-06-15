<?php
/**
 * Класс для расчета оплаты сотрудников по сделкам
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Deal_Calculator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Расчет оплаты сотрудника
     * 
     * Формула: Оплата = work_cost × (work_hours / standard_hours) × (percent / 100)
     * 
     * @param float $work_cost Стоимость работ
     * @param float $work_hours Фактические часы работы
     * @param float $standard_hours Нормативные часы
     * @param float $percent Процент сотрудника
     * @return array Массив с результатами расчета
     */
    public function calculate($work_cost, $work_hours, $standard_hours, $percent) {
        // Приведение типов и защита от деления на ноль
        $work_cost = floatval($work_cost);
        $work_hours = floatval($work_hours);
        $standard_hours = floatval($standard_hours) > 0 ? floatval($standard_hours) : 1;
        $percent = floatval($percent);
        
        // Коэффициент выполнения нормы
        $completion_ratio = $work_hours / $standard_hours;
        
        // Коэффициент процента
        $percent_ratio = $percent / 100;
        
        // Итоговая оплата
        $payment = $work_cost * $completion_ratio * $percent_ratio;
        $payment = round($payment, 2);
        
        return [
            'work_cost' => $work_cost,
            'work_hours' => $work_hours,
            'standard_hours' => $standard_hours,
            'completion_ratio' => round($completion_ratio, 4),
            'percent' => $percent,
            'percent_ratio' => round($percent_ratio, 4),
            'payment' => $payment,
            'payment_formatted' => number_format($payment, 0, ',', ' ') . ' ₽'
        ];
    }
    
    /**
     * Расчет с детальным разбором
     */
    public function calculate_detailed($work_cost, $work_hours, $standard_hours, $percent) {
        $result = $this->calculate($work_cost, $work_hours, $standard_hours, $percent);
        
        $result['details'] = [
            'formula' => 'Оплата = Стоимость работ × (Факт часы / Норма часы) × (Процент / 100)',
            'step1' => "Стоимость работ: {$result['work_cost']} ₽",
            'step2' => "Коэффициент нормы: {$result['work_hours']} / {$result['standard_hours']} = {$result['completion_ratio']}",
            'step3' => "Коэффициент процента: {$result['percent']}% / 100 = {$result['percent_ratio']}",
            'step4' => "Расчет: {$result['work_cost']} × {$result['completion_ratio']} × {$result['percent_ratio']} = {$result['payment']} ₽"
        ];
        
        return $result;
    }
    
    /**
     * Расчет оплаты для нескольких сотрудников по одной сделке
     */
    public function calculate_multi_employee($work_cost, $work_hours, $standard_hours, $employees) {
        $results = [];
        $total_payment = 0;
        
        foreach ($employees as $employee) {
            $result = $this->calculate(
                $work_cost,
                $work_hours,
                $standard_hours,
                $employee['percent']
            );
            
            $results[$employee['id']] = [
                'name' => $employee['name'],
                'percent' => $employee['percent'],
                'payment' => $result['payment'],
                'payment_formatted' => $result['payment_formatted']
            ];
            
            $total_payment += $result['payment'];
        }
        
        return [
            'employees' => $results,
            'total_payment' => $total_payment,
            'total_payment_formatted' => number_format($total_payment, 0, ',', ' ') . ' ₽'
        ];
    }
    
    /**
     * Расчет эффективности сотрудника (за месяц)
     */
    public function calculate_employee_efficiency($employee_id, $month, $year) {
        global $wpdb;
        $table_deals = $wpdb->prefix . 'akpp_deals';
        
        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_deals} 
            WHERE employee_id = %d 
            AND MONTH(created_at) = %d 
            AND YEAR(created_at) = %d 
            AND status = 'completed'",
            $employee_id,
            $month,
            $year
        ));
        
        $total_work_cost = 0;
        $total_work_hours = 0;
        $total_standard_hours = 0;
        $total_payment = 0;
        
        foreach ($deals as $deal) {
            $result = $this->calculate(
                $deal->work_cost,
                $deal->work_hours,
                $deal->standard_hours,
                $deal->employee_percent
            );
            
            $total_work_cost += $deal->work_cost;
            $total_work_hours += $deal->work_hours;
            $total_standard_hours += $deal->standard_hours;
            $total_payment += $result['payment'];
        }
        
        $avg_completion_ratio = $total_standard_hours > 0 ? $total_work_hours / $total_standard_hours : 0;
        
        return [
            'employee_id' => $employee_id,
            'month' => $month,
            'year' => $year,
            'deals_count' => count($deals),
            'total_work_cost' => $total_work_cost,
            'total_work_cost_formatted' => number_format($total_work_cost, 0, ',', ' ') . ' ₽',
            'total_work_hours' => $total_work_hours,
            'total_standard_hours' => $total_standard_hours,
            'avg_completion_ratio' => round($avg_completion_ratio, 2),
            'total_payment' => $total_payment,
            'total_payment_formatted' => number_format($total_payment, 0, ',', ' ') . ' ₽'
        ];
    }
    
    /**
     * Прогнозирование оплаты по сделке
     */
    public function predict_payment($work_cost, $standard_hours, $percent) {
        $scenarios = [];
        
        // Сценарии выполнения: 50%, 75%, 100%, 125%, 150%
        $scenario_factors = [0.5, 0.75, 1.0, 1.25, 1.5];
        
        foreach ($scenario_factors as $factor) {
            $work_hours = $standard_hours * $factor;
            $result = $this->calculate($work_cost, $work_hours, $standard_hours, $percent);
            
            $scenarios[] = [
                'factor' => $factor * 100 . '%',
                'work_hours' => round($work_hours, 1),
                'payment' => $result['payment'],
                'payment_formatted' => $result['payment_formatted']
            ];
        }
        
        return $scenarios;
    }
    
    /**
     * Расчет рентабельности сделки
     */
    public function calculate_profitability($deal_id) {
        global $wpdb;
        $table_deals = $wpdb->prefix . 'akpp_deals';
        $table_deal_parts = $wpdb->prefix . 'akpp_deal_parts';
        
        $deal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_deals} WHERE id = %d",
            $deal_id
        ));
        
        if (!$deal) {
            return false;
        }
        
        $parts = $wpdb->get_results($wpdb->prepare(
            "SELECT SUM(total) as parts_total FROM {$table_deal_parts} WHERE deal_id = %d",
            $deal_id
        ));
        
        $parts_total = $parts[0]->parts_total ?? 0;
        
        // Расчет оплаты сотрудника
        $payment_result = $this->calculate(
            $deal->work_cost,
            $deal->work_hours,
            $deal->standard_hours,
            $deal->employee_percent
        );
        
        $total_expenses = $parts_total + $payment_result['payment'];
        $total_income = $deal->work_cost + $parts_total;
        $profit = $total_income - $total_expenses;
        $profit_margin = $total_income > 0 ? ($profit / $total_income) * 100 : 0;
        
        return [
            'deal_id' => $deal_id,
            'total_income' => $total_income,
            'total_income_formatted' => number_format($total_income, 0, ',', ' ') . ' ₽',
            'total_expenses' => $total_expenses,
            'total_expenses_formatted' => number_format($total_expenses, 0, ',', ' ') . ' ₽',
            'parts_cost' => $parts_total,
            'parts_cost_formatted' => number_format($parts_total, 0, ',', ' ') . ' ₽',
            'labor_cost' => $payment_result['payment'],
            'labor_cost_formatted' => $payment_result['payment_formatted'],
            'profit' => $profit,
            'profit_formatted' => number_format($profit, 0, ',', ' ') . ' ₽',
            'profit_margin' => round($profit_margin, 2) . '%'
        ];
    }
    
    /**
     * Валидация входных данных
     */
    public function validate_inputs($work_cost, $work_hours, $standard_hours, $percent) {
        $errors = [];
        
        if ($work_cost < 0) {
            $errors[] = 'Стоимость работ не может быть отрицательной';
        }
        
        if ($work_hours < 0) {
            $errors[] = 'Фактические часы не могут быть отрицательными';
        }
        
        if ($standard_hours <= 0) {
            $errors[] = 'Нормативные часы должны быть больше нуля';
        }
        
        if ($percent < 0 || $percent > 100) {
            $errors[] = 'Процент сотрудника должен быть от 0 до 100';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Получение рекомендуемого процента для сотрудника
     */
    public function get_recommended_percent($role, $experience_years) {
        $base_percent = [
            'master' => 40,
            'senior_master' => 50,
            'lead_master' => 60,
            'foreman' => 30,
            'assistant' => 20
        ];
        
        $percent = $base_percent[$role] ?? 40;
        
        // Добавляем бонус за опыт
        if ($experience_years >= 5) {
            $percent += 10;
        } elseif ($experience_years >= 3) {
            $percent += 5;
        }
        
        return min($percent, 70);
    }
}
