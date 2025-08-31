<?php

namespace App\Http\Controllers;

use App\Models\Reports\MonthlyYearOverYearOrders;
use App\Models\Reports\OpenedOrders;
use App\Models\Reports\OrderServiceTypes;
use App\Models\Reports\OrdersPaymentStatus;
use App\Models\Reports\RegisteredAgentPaymentStatus;
use App\Models\Reports\Reports;
use App\Models\Reports\SalesRepOrders;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public string $startDate;
    public string $finishDate;

    private static array $methods = [
        Reports::WEEKLY_NEW_ORDERS => 'weeklyNewOrders',
        Reports::YEAR_OVER_YEAR => 'yearOverYear',
        Reports::SALES_REP_ORDERS => 'salesRepOrders',
        Reports::ORDERS_PAYMENT_STATUS => 'ordersPaymentStatus',
        Reports::REGISTERED_AGENT_BY_STATE => 'registeredAgentByState',
        Reports::ORDER_STATUSES => 'orderStatuses',
        Reports::REGISTERED_AGENT_PAYMENT_STATUS => 'registeredAgentPaymentStatus',
        Reports::ORDER_SERVICE_TYPES => 'orderServiceTypes',
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole(Role::ADMIN)) {
                abort(404);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $startOfThisWeek = now()->startOfWeek();
        $startOfThisMonth = now()->startOfMonth();
        $startOfThisYear = now()->startOfYear();

        $data = [
            'title' => 'Reports',
            'ordersOpenedThisWeek' => OpenedOrders::getOpenedOrdersData($startOfThisWeek->toDateString()),
            'ordersOpenedThisMonth' => OpenedOrders::getOpenedOrdersData($startOfThisMonth->toDateString()),
            'ordersOpenedThisYear' => OpenedOrders::getOpenedOrdersData($startOfThisYear->toDateString()),
        ];

        return view('reports.main-report', $data);
    }

    public function details($reportId)
    {
        // Check if the method exists for the report ID
        if (!isset(self::$methods[$reportId]) || !method_exists($this, self::$methods[$reportId])) {
            abort(404);
        }

        $this->resolveDateRange();

        $methodName = self::$methods[$reportId];
        return $this->$methodName();
    }

    private function resolveDateRange():void
    {
        if (now()->day <= 5) {
            $this->startDate = Carbon::now()->startOfMonth()->subMonth();
            $this->finishDate = $this->startDate->clone()->endOfMonth()->toDateString();
            $this->startDate = $this->startDate->toDateString();
        } else {
            $this->startDate = Carbon::now()->startOfMonth()->toDateString();
            $this->finishDate = Carbon::now()->toDateString();
        }
    }

    private function weeklyNewOrders()
    {
        $data = [
            'title' => 'Weekly New Orders',
        ];
        return view('reports.weekly-new-orders', $data);
    }

    private function yearOverYear()
    {
        $currentYear = Carbon::now()->year;
        $startMonth = 1;
        $endMonth = 12;
        $years = range($currentYear - 3, $currentYear);

        $yearOverYearData = MonthlyYearOverYearOrders::getData($years, $startMonth, $endMonth);
        $yearOverYearData['categories'][12] = 'Total';
        foreach ($yearOverYearData['series'] as $k => $v) {
            $total = 0;
            foreach ($v['data'] as $periodTotal) {
                $total += $periodTotal;
            }
            $yearOverYearData['series'][$k]['data'][12] = $total;
        }
        $data = [
            'title' => 'Year Over Year',
            'yearOverYearData' => $yearOverYearData,
            'years' => $years
        ];

        return view('reports.year-over-year', $data);
    }

    private function salesRepOrders()
    {
        $data = [
            'title' => 'Sales Rep Orders',
            'salesRepOrdersData' => SalesRepOrders::getSalesRepOrdersData($this->startDate, $this->finishDate),
        ];

        return view('reports.sales-rep-orders', $data);
    }

    private function ordersPaymentStatus()
    {
        $data = [
            'title' => 'Orders Payment Status',
            'ordersPaymentStatusData' => OrdersPaymentStatus::getMonthlyOrdersData($this->startDate, $this->finishDate),
        ];

        return view('reports.orders-payment-status', $data);
    }

    private function orderStatuses()
    {
        $data = [
            'title' => 'Order Statuses',
        ];

        return view('reports.report-order-statuses', $data);
    }

    private function registeredAgentPaymentStatus()
    {
        $data = [
            'title' => 'Registered Agent Payment Status',
            'registeredAgentPaymentStatusData' => RegisteredAgentPaymentStatus::getRegisteredAgentPaymentStatusData(
                $this->startDate,
                $this->finishDate
            ),
        ];

        return view('reports.registered-agent-payment-status', $data);
    }

    private function orderServiceTypes()
    {
        $data = [
            'title' => 'Service Types',
            'orderServiceTypesData' => OrderServiceTypes::getOrdersServiceTypeData(
                $this->startDate,
                $this->finishDate
            ),
        ];

        return view('reports.report-service-types', $data);
    }
}
