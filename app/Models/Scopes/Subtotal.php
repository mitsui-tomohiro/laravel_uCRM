<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class Subtotal implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $sql = 'SELECT purchases.id as id
            , items.id as item_id
            , item_purchase.id as pivot_id
            , items.price * item_purchase.quantity as subtotal
            , customers.id as customer_id
            , customers.name as customer_name
            , items.name as item_name
            , items.price as item_price
            , item_purchase.quantity
            , purchases.status
            , purchases.created_at
            , purchases.updated_at
        FROM purchases
        LEFT JOIN item_purchase
            ON purchases.id = item_purchase.purchase_id
        LEFT JOIN items
            ON item_purchase.item_id = items.id
        LEFT JOIN customers
            ON purchases.customer_id = customers.id
        ';

        $builder->fromSub($sql, 'order_subtotals');
    }
}
