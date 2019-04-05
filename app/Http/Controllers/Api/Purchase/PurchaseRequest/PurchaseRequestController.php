<?php

namespace App\Http\Controllers\Api\Purchase\PurchaseRequest;

use App\Model\Form;
use App\Model\Master\Item;
use Illuminate\Http\Request;
use App\Model\Master\Supplier;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ApiResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCollection;
use App\Model\HumanResource\Employee\Employee;
use App\Model\Purchase\PurchaseRequest\PurchaseRequest;
use App\Model\Purchase\PurchaseRequest\PurchaseRequestItem;
use App\Http\Requests\Purchase\PurchaseRequest\PurchaseRequest\StorePurchaseRequestRequest;

class PurchaseRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return ApiCollection
     */
    public function index(Request $request)
    {
        $purchaseRequests = PurchaseRequest::eloquentFilter($request)
            ->joinForm()
            ->leftJoin(Supplier::getTableName(), PurchaseRequest::getTableName('supplier_id'), '=', Supplier::getTableName('id'))
            ->leftJoin(Employee::getTableName(), PurchaseRequest::getTableName('employee_id'), '=', Employee::getTableName('id'))
            ->leftJoin(PurchaseRequestItem::getTableName(), PurchaseRequestItem::getTableName('purchase_request_id'), '=', PurchaseRequest::getTableName('id'))
            ->leftJoin(Item::getTableName(), Item::getTableName('id'), '=', PurchaseRequestItem::getTableName('item_id'))
            ->notArchived()
            ->groupBy('forms.id')
            ->with('form');

        $purchaseRequests = pagination($purchaseRequests, $request->get('limit'));

        return new ApiCollection($purchaseRequests);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Request :
     *  - required_date (Date)
     *  - number (String)
     *  - date (Date)
     *  - required_date (Date)
     *  - employee_id (Int)
     *  - supplier_id (Int, Optional)
     *  - items (Array) :
     *      - item_id (Int)
     *      - quantity (Decimal)
     *      - unit (String)
     *      - converter (Decimal)
     *      - price (Decimal)
     *      - description (String Optional)
     *      - allocation_id (Int Optional)
     *  - services (Array) :
     *      - service_id (Int)
     *      - quantity (Decimal)
     *      - price (Decimal)
     *      - description (String Optional)
     *      - allocation_id (Int Optional)
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Throwable
     * @return ApiResource
     */
    public function store(StorePurchaseRequestRequest $request)
    {
        $result = DB::connection('tenant')->transaction(function () use ($request) {
            $purchaseRequest = PurchaseRequest::create($request->all());
            $purchaseRequest
                ->load('form')
                ->load('employee')
                ->load('supplier')
                ->load('items.item')
                ->load('items.allocation')
                ->load('services.service')
                ->load('services.allocation');

            return new ApiResource($purchaseRequest);
        });

        return $result;
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param int $id
     * @return ApiResource
     */
    public function show(Request $request, $id)
    {
        $purchaseRequest = PurchaseRequest::eloquentFilter($request)
            ->with('form')
            ->with('employee')
            ->with('supplier')
            ->with('items.item')
            ->with('items.allocation')
            ->with('services.service')
            ->with('services.allocation')
            ->with('approvers.requestedTo')
            ->with('approvers.requestedBy')
            ->findOrFail($id);

        return new ApiResource($purchaseRequest);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param PurchaseRequest $purchaseRequest
     * @return ApiResource
     * @throws \Throwable
     */
    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $error = PurchaseRequest::isAllowedToUpdate($purchaseRequest);
        if ($error) {
            return $error;
        }

        $result = DB::connection('tenant')->transaction(function () use ($request, $purchaseRequest) {
            $purchaseRequest->load('form', 'purchaseOrders');

            $archivedForm = Form::archive($purchaseRequest->form);

            $purchaseRequest = PurchaseRequest::create($request->all(), $archivedForm);
            $purchaseRequest
                ->load('form')
                ->load('employee')
                ->load('supplier')
                ->load('items.item')
                ->load('items.allocation')
                ->load('services.service')
                ->load('services.allocation');

            return new ApiResource($purchaseRequest);
        });

        return $result;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param PurchaseRequest $purchaseRequest
     * @return \Illuminate\Http\Response
     */
    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $error = PurchaseRequest::isAllowedToUpdate($purchaseRequest);
        if ($error) {
            return $error;
        }

        Form::cancel($purchaseRequest->form);

        return response()->json([], 204);
    }
}
