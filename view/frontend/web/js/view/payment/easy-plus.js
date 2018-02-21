/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payumea_generic',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/generic-method'
            },
            {
                type: 'payumea_discoverymiles',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/discoverymiles-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);