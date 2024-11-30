<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PostView;
use App\Models\Post;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->activeTheme = active_theme();
    }

    /**
     * Display the page
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $days = $scans = [];
        $startDate = $startDate->copy();
        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            $days[] = date_formating($date, 'd M');
            $scans[date_formating($date, 'd M')] = 0;
        }

        $post_ids = [];
        foreach ( request()->user()->posts as $post ) {
            $post_ids[] = $post->id;
        }

        if (!empty($post_ids)) {
            $post_scans = PostView::where('date', '>=', Carbon::now()->startOfMonth())
                ->selectRaw('DATE(date) as created, COUNT(1) as scans')
                ->whereIn('post_id', $post_ids)
                ->groupBy('created')
                ->get();

            foreach ($post_scans as $data) {
                $scans[date_formating($data->created, 'd M')] = $data->scans;
            }
        }
        $scans = array_values($scans);
        
        $total_posts = Post::all()->count();
        $total_scans = PostView::all()->count();
        $total_earnings = Transaction::where('status',Transaction::STATUS_SUCCESS)->sum('amount');
        $total_users = User::all()->count();
        $current_month_posts = Post::whereMonth('created_at', Carbon::now()->month)->count();
        $current_month_scans = PostView::whereMonth('date', Carbon::now()->month)->count();
        $current_month_earnings = Transaction::whereMonth('created_at', Carbon::now()->month)->where('status', "success")->sum('amount');
        $current_month_users = User::whereMonth('created_at', Carbon::now()->month)->count();

        $transactions = Transaction::whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_PENDING])->whereHas('user')->orderbyDesc('id')->limit(6)->get();
        $users = User::orderbyDesc('id')->limit(6)->get();

        /* Earning Chart data */
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        $dates = collect();
        $startDate = $startDate->copy();
        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            $dates->put($date->format('Y-m-d'), 0);
        }

        $earnings = Transaction::where('status', Transaction::STATUS_SUCCESS)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->selectRaw('DATE(created_at) as date, SUM(amount) as sum')
            ->groupBy('date')
            ->pluck('sum', 'date');

        $getEarningsData = $dates->merge($earnings);
        $earningsLabels = [];
        $earningsData = [];
        foreach ($getEarningsData as $key => $value) {
            $earningsLabels[] = date_formating($key, 'd M');
            $earningsData[] = number_format((float) $value, 2);
        }
        $suggestedMax = (max($earningsData) > 9) ? max($earningsData) + 2 : 10;
        $earningData = ['labels' => $earningsLabels, 'data' => $earningsData, 'max' => $suggestedMax];

        /* Users Chart data */
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        $dates = collect();
        $startDate = $startDate->copy();
        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            $dates->put($date->format('Y-m-d'), 0);
        }

        $usersChart = User::where('created_at', '>=', Carbon::now()->startOfWeek())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $usersRecordData = $dates->merge($usersChart);
        $usersLabels = [];
        $usersData = [];
        foreach ($usersRecordData as $key => $value) {
            $usersLabels[] = date_formating($key, 'd M');
            $usersData[] = $value;
        }
        $suggestedMax = (max($usersData) > 9) ? max($usersData) + 2 : 10;
        $usersData = ['labels' => $usersLabels, 'data' => $usersData, 'max' => $suggestedMax];

        return view($this->activeTheme.'.user.dashboard', compact('days', 'scans',
            'total_posts',
            'total_scans',
            'total_earnings',
            'total_users',
            'current_month_posts',
            'current_month_scans',
            'current_month_earnings',
            'current_month_users',
            'transactions',
            'users',
            'earningData',
            'usersData'
        ));
    }
}
