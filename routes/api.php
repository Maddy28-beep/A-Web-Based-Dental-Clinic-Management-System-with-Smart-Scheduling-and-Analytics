<?php

use App\Http\Controllers\Api\AllergyController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentPatientController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\BillingSummaryController;
use App\Http\Controllers\Api\BillPaymentController;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\DentistController;
use App\Http\Controllers\Api\DentistLeaveController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MedicalHistoryController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PaymentRefundController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\ProcedurePriceController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ToothChartingController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\XrayFileController;
use App\Http\Middleware\EnsureStaffSession;
use Illuminate\Support\Facades\Route;

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/clinic/closures', [ClinicController::class, 'listClosures']);

Route::get('/dentists', [DentistController::class, 'index']);
Route::get('/dentists/{dentist}/availability', [DentistController::class, 'availability']);
Route::post('/appointments', [AppointmentController::class, 'store']);
Route::get('/appointments/reference/{bookingReferenceCode}', [AppointmentController::class, 'lookupByReference']);

Route::get('/forecast/busy-days', [ForecastController::class, 'busyDays']);

Route::middleware(['web', 'auth', EnsureStaffSession::class])->group(function () {
    Route::post('/services', [ServiceController::class, 'store']);
    Route::post('/dentists', [DentistController::class, 'store']);
    Route::post('/dentists/{dentist}/leave', [DentistLeaveController::class, 'store']);

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments/check-in', [AppointmentController::class, 'checkInByReference']);
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    Route::post('/clinic/closures', [ClinicController::class, 'storeClosure']);
    Route::post('/clinic/closed-hours', [ClinicController::class, 'storeClosedHour']);

    Route::get('/patients', [PatientController::class, 'index']);
    Route::post('/patients', [PatientController::class, 'store']);
    Route::get('/patients/{patient}/teeth', [ToothChartingController::class, 'index']);
    Route::get('/patients/{patient}/teeth/{toothCode}/history', [ToothChartingController::class, 'history']);
    Route::post('/patients/{patient}/teeth/{toothCode}/records', [ToothChartingController::class, 'storeRecord']);

    Route::get('/patients/{patient}/allergies', [AllergyController::class, 'index']);
    Route::post('/patients/{patient}/allergies', [AllergyController::class, 'store']);
    Route::patch('/patients/{patient}/allergies/{allergy}', [AllergyController::class, 'update']);

    Route::get('/patients/{patient}/medical-history', [MedicalHistoryController::class, 'index']);
    Route::post('/patients/{patient}/medical-history', [MedicalHistoryController::class, 'store']);

    Route::get('/patients/{patient}/visits', [VisitController::class, 'index']);
    Route::post('/patients/{patient}/visits', [VisitController::class, 'store']);

    Route::get('/patients/{patient}/procedures', [ProcedureController::class, 'index']);
    Route::post('/patients/{patient}/procedures', [ProcedureController::class, 'store']);
    Route::get('/patients/{patient}/follow-ups', [ProcedureController::class, 'followUps']);
    Route::get('/follow-ups/due', [ProcedureController::class, 'dueFollowUps']);
    Route::get('/patients/{patient}/procedures/{procedure}/highlights', [ToothChartingController::class, 'procedureHighlights']);

    Route::get('/patients/{patient}/xrays', [XrayFileController::class, 'index']);
    Route::post('/patients/{patient}/xrays', [XrayFileController::class, 'store']);
    Route::get('/patients/{patient}/xrays/{xray}', [XrayFileController::class, 'show'])->name('api.xrays.show');

    Route::post('/appointments/{appointment}/convert-to-patient', [AppointmentPatientController::class, 'convertToPatient']);

    Route::get('/procedure-prices', [ProcedurePriceController::class, 'index']);
    Route::post('/procedure-prices', [ProcedurePriceController::class, 'store']);
    Route::patch('/procedure-prices/{procedurePrice}', [ProcedurePriceController::class, 'update']);

    Route::get('/inventory/items', [InventoryController::class, 'index']);
    Route::post('/inventory/items', [InventoryController::class, 'store']);
    Route::patch('/inventory/items/{inventoryItem}', [InventoryController::class, 'update']);
    Route::post('/inventory/items/{inventoryItem}/restock', [InventoryController::class, 'restock']);
    Route::post('/inventory/items/{inventoryItem}/adjust', [InventoryController::class, 'adjust']);
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
    Route::get('/inventory/reports/monthly', [InventoryController::class, 'monthlyReport']);

    Route::get('/bills', [BillController::class, 'index']);
    Route::get('/bills/{bill}', [BillController::class, 'show']);
    Route::post('/patients/{patient}/bills', [BillController::class, 'store']);
    Route::post('/bills/{bill}/lock', [BillController::class, 'lock']);

    Route::post('/bills/{bill}/payments', [BillPaymentController::class, 'store']);
    Route::post('/payments/{payment}/refunds', [PaymentRefundController::class, 'store']);

    Route::get('/billing/summary', [BillingSummaryController::class, 'show']);

    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('/analytics/procedures/top', [AnalyticsController::class, 'topProcedures']);
    Route::get('/analytics/procedures/types', [AnalyticsController::class, 'procedureTypes']);
    Route::get('/analytics/appointments/peak-days', [AnalyticsController::class, 'peakDays']);
    Route::get('/analytics/revenue/monthly', [AnalyticsController::class, 'revenueMonthly']);
    Route::get('/analytics/patients/retention', [AnalyticsController::class, 'retention']);

    Route::get('/analytics/procedures/{procedureType}/patients', [AnalyticsController::class, 'procedurePatients']);
    Route::get('/analytics/peak-days/{dayOfWeek}/appointments', [AnalyticsController::class, 'peakDayAppointments']);
    Route::get('/analytics/revenue/{month}/receipts', [AnalyticsController::class, 'revenueReceipts']);
});
