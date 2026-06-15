<?php
if (!defined('ABSPATH')) exit;

class AKPP_Deal_Calculator {
    
    public function __construct() {
        // AJAX хук для динамического расчета в форме сделки
        add_action('wp_ajax_akpp_calculate_deal', [$this, 'ajax_calculate_deal']);
    }

    /**
     * Полный расчет параметров сделки
     *
     * @param float $hours         Количество нормо-часов
     * @param float $hourly_rate   Стоимость одного нормо-часа (для клиента)
     * @param float $parts_cost    Себестоимость запчастей
     * @param float $parts_markup  Наценка на запчасти в процентах (например, 30)
     * @param float $emp_percent   Процент сотрудника от стоимости работ (например, 40)
     * @return array Массив с рассчитанными значениями
     */
    public function calculate($hours, $hourly_rate, $parts_cost, $parts_markup, $emp_percent) {
        // 1. Расчет стоимости работ для клиента
        $labor_cost = floatval($hours) * floatval($hourly_rate);

        // 2. Расчет стоимости запчастей с наценкой
        $parts_markup_multiplier = 1 + (floatval($parts_markup) / 100);
        $total_parts_cost = floatval($parts_cost) * $parts_markup_multiplier;
        $parts_profit = $total_parts_cost - floatval($parts_cost);

        // 3. Итоговая сумма сделки
        $grand_total = $labor_cost + $total_parts_cost;

        // 4. Расчет оплаты сотрудника
        // Обычно сотрудник получает процент только от стоимости РАБОТ, а не от запчастей.
        // Если нужна оплата и от запчастей, формулу можно легко изменить.
        $employee_payout = $labor_cost * (floatval($emp_percent) / 100);

        // 5. Чистая прибыль компании
        $company_profit = $grand_total - $total_parts_cost - $employee_payout; 
        // (Упрощенно: Прибыль = Работы + Наценка на запчасти - Выплата сотруднику)
        // Более точная: ($labor_cost - $employee_payout) + $parts_profit

        return [
            'labor_cost'       => round($labor_cost, 2),
            'total_parts_cost' => round($total_parts_cost, 2),
            'parts_profit'     => round($parts_profit, 2),
            'grand_total'      => round($grand_total, 2),
            'employee_payout'  => round($employee_payout, 2),
            'company_profit'   => round(($labor_cost - $employee_payout) + $parts_profit, 2)
        ];
    }

    /**
     * Расчет сложности работы (коэффициент)
     * Иногда стандартные часы умножаются на коэффициент сложности (например, ржавые болты +20%)
     */
    public function apply_complexity_factor($base_hours, $complexity_percent) {
        $multiplier = 1 + (floatval($complexity_percent) / 100);
        return round(floatval($base_hours) * $multiplier, 2);
    }

    /**
     * AJAX обработчик для динамического расчета в админке
     */
    public function ajax_calculate_deal() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        // Получаем данные из формы
        $hours = floatval($_POST['hours'] ?? 0);
        $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
        $parts_cost = floatval($_POST['parts_cost'] ?? 0);
        $parts_markup = floatval($_POST['parts_markup'] ?? 0);
        $emp_percent = floatval($_POST['emp_percent'] ?? 0);
        $complexity = floatval($_POST['complexity'] ?? 0);

        // Применяем коэффициент сложности к часам, если он есть
        if ($complexity > 0) {
            $hours = $this->apply_complexity_factor($hours, $complexity);
        }

        // Выполняем расчет
        $result = $this->calculate($hours, $hourly_rate, $parts_cost, $parts_markup, $emp_percent);
        
        // Добавляем итоговые часы в ответ
        $result['final_hours'] = $hours;

        wp_send_json_success($result);
    }
}
