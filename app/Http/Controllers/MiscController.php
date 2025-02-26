<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Helpers\Helper;
use Carbon\Carbon;
use DateTimeZone;
use Log;

class MiscController extends Controller
{
    /**
     * Converts currency based on parameters passed.
     *
     * @param  Request $request
     * @return Response
     */
    public function currency(Request $request)
    {
        $value = $request->input('value', null);
        $from = $request->input('from', null);
        $to = $request->input('to', null);
        $round = intval($request->input('round', 2));
        $currencies = config('currency.currencies');

        $listUrl = route('misc.currency', 'currency') . '?list';

        if ($request->exists('list')) {
            return Helper::text('Available currencies: ' . implode(', ', $currencies));
        }

        if (empty($value)) {
            return Helper::text('The "value" parameter has to be specified');
        }

        if (empty($from)) {
            return Helper::text('The "from" parameter has to be specified');
        }

        if (empty($to)) {
            return Helper::text('The "to" parameter has to be specified');
        }

        $value = floatval(str_replace(',', '', $value));
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ((int) $value === 0) {
            $value = 1;
        }

        if (!in_array($from, $currencies)) {
            return Helper::text(sprintf('Invalid "from" currency specified (%s) - Available currencies can be found here: %s', $from, $listUrl));
        }

        if (!in_array($to, $currencies)) {
            return Helper::text(sprintf('Invalid "to" currency specified (%s) - Available currencies can be found here: %s', $to, $listUrl));
        }

        $apiUrl = sprintf('https://api.exchangerate.host/latest?base=%s&symbols=%s', $from, $to);
        try {
            $convert = Helper::get($apiUrl);
        }
        catch (Exception $ex) {
            Log::error('/misc/currency request error: ' . $ex->getMessage());
            return Helper::text('An error has occurred retrieving exchange rates');
        }

        if (empty($convert)) {
            return Helper::text('An error has occurred retrieving exchange rates');
        }

        if ($convert['success'] === false) {
            if (!empty($convert['error'])) {
                return Helper::text('An error occurred retrieving exchange rates: ' . $convert['error']['type']);
            }

            return Helper::text('An error has occurred retrieving exchange rates.');
        }

        $calculate = round($value * $convert['rates'][$to], $round);
        return Helper::text(sprintf('%s %s = %s %s', $value, $from, $calculate, $to));
    }

    /**
     * Display the current time in the specified timezone.
     *
     * @param  Request $request
     * @return Response
     */
    public function time(Request $request)
    {
        $format = $request->input('format', 'h:i:s A T');
        $tz = $request->input('timezone', null);
        $timezones = DateTimeZone::listIdentifiers();
        $tzlist = implode(PHP_EOL, $timezones);
        if (empty($tz)) {
            return Helper::text(sprintf('-- Parameter `timezone` needs to be specified - Available timezones can be found here: %s', route('misc.timezones')));
        }

        if (!in_array($tz, $timezones)) {
            return Helper::text(sprintf('-- Invalid timezone specified ("%s") - Available timezones can be found here: %s', $tz, route('misc.timezones')));
        }

        $time = Carbon::now()
                ->tz($tz)
                ->format($format);

        return Helper::text($time);
    }

    /**
     * Lists the supported timezones.
     *
     * @param Request $request
     * @return Response
     */
    public function timezones(Request $request)
    {
        $timezones = DateTimeZone::listIdentifiers();

        if ($request->wantsJson()) {
            return Helper::json($timezones);
        }

        return Helper::text(implode(PHP_EOL, $timezones));
    }
}
