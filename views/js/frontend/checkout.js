/**
 * Trust Payments Prestashop
 *
 * This Prestashop module enables to process payments with Trust Payments (https://www.trustpayments.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {

    var trustpayments_checkout = {


        payment_methods : {},
        configuration_id: null,
        cartHash: null,
        processing: false,


        init : function () {
            if ($('#trustpayments-iframe-handler').length) {
                $(".trustpayments-method-data").each(function (key, element) {
                    $("#"+psId+"-container").parent().remove();

                });
                return;
            }
            this.add_listeners();
            this.modify_content();
        },

        modify_content : function () {
            $(".trustpayments-method-data").each(function (key, element) {
                var infoId = $(element).closest('div.additional-information').attr('id');
                var psId = infoId.substring(0, infoId.indexOf('-additional-information'));
                var psContainer = $('#'+psId+'-container');
                psContainer.children('label').children('img').addClass('trustpayments-image');
                psContainer.addClass('trustpayments-payment-option');
                var fee = $(element).closest("div.additional-information").find(".trustpayments-payment-fee");
                psContainer.children("label").append(fee);
                $("#"+psId).data("trustpayments-method-id", $(element).data("method-id")).data("trustpayments-configuration-id", $(element).data("configuration-id"));
            });
        },

        add_listeners : function () {
            var self = this;
            $("input[name='payment-option']").off("click.trustpayments").on("click.trustpayments", {
                self : this
                }, this.payment_method_click);
            $('form.trustpayments-payment-form').each(function () {
                this.originalSubmit = this.submit;
                this.submit = function (evt) {
                    self.process_submit_button($(this).data('method-id'));
                }
            });
            $('form.trustpayments-amount-error').each(function () {
                this.submit = function (evt) {
                    //This is the info message and sould not be used as method
                }
            });


        },

        payment_method_click : function (event) {
            var self = event.data.self;
            var current_method = self.get_selected_payment_method();
            var postData;
            if (current_method.data('module-name') === 'trustpayments') {
                postData = "methodId="+current_method.data("trustpayments-method-id");
            }
            $.ajax({
                type: 'POST',
                url: trustPaymentsCheckoutUrl,
                data: postData,
                dataType: "json",
                success:  function (response, textStatus, jqXHR) {
                    if ( response.result === 'success') {
                        $("#js-checkout-summary").fadeOut("slow", function () {
                            var div = $(response.preview).hide();
                            $(this).replaceWith(div);
                            $("#js-checkout-summary").fadeIn("slow");
                        });
                        $("#order-items").fadeOut("slow", function () {
                            var confirmation = $(response.summary).find("#order-items");
                            $(confirmation).hide();
                            $(this).replaceWith(confirmation);
                            $("#order-items").fadeIn("slow");
                        });
                        self.cartHash = response.cartHash
                    } else {
                          window.location.href = window.location.href;
                    }
                },
                error:      function (jqXHR, textStatus, errorThrown) {
                    window.location.href = window.location.href;
                    return;
                }
            });
            if (current_method.data('module-name') === 'trustpayments') {
                self.register_method(current_method.data("trustpayments-method-id"), current_method.data("trustpayments-configuration-id"), "trustpayments-"+current_method.data("trustpayments-method-id"));
            }

        },

        get_selected_payment_method : function () {
               return $("input[name='payment-option']:checked");
        },

        register_method : function (method_id, configuration_id, container_id) {

            if (typeof window.trustpaymentsIFrameCheckoutHandler == 'undefined') {
                $('#trustpayments-loader-'+method_id).remove();
                this.payment_methods[method_id] = {
                    configuration_id : configuration_id,
                    container_id : container_id,
                    handler: null
                };
                return;
            }

            if (typeof this.payment_methods[method_id] != 'undefined'
                && $('#' + container_id).find("iframe").length > 0) {
                return;
            }
            var self = this;
            this.payment_methods[method_id] = {
                configuration_id : configuration_id,
                container_id : container_id,
                handler : window.trustpaymentsIFrameCheckoutHandler(configuration_id)
            };
            this.payment_methods[method_id].handler
                .setValidationCallback(function (validation_result) {
                    self.process_validation(method_id, validation_result);
                });
            this.payment_methods[method_id].handler.setInitializeCallback(function () {
                $('#trustpayments-loader-'+method_id).remove();
                $('#trustpayments-iframe-possible-'+method_id).remove();
            });

            this.payment_methods[method_id].handler
                .create(self.payment_methods[method_id].container_id);
        },

        process_submit_button : function (method_id) {
            if(!this.processing) {
                this.disable_pay_button();
                if (this.payment_methods[method_id].handler == null) {
                    this.create_order(method_id);
                    return;
                }
                this.payment_methods[method_id].handler.validate();
            }
        },

        disable_pay_button : function () {
            $('#payment-confirmation button').prop('disabled', true);
            this.processing = true;
            this.show_loading_spinner();
        },

        enable_pay_button : function (errors) {
            $('#payment-confirmation button').prop('disabled', false);
            this.processing = false;
            this.hide_loading_spinner();
            this.remove_existing_errors();
            if(errors) {
                this.show_new_errors(errors);
            }
        },

        process_validation : function (method_id, validation_result) {
            if (validation_result.success) {
                this.create_order(method_id);
            } else {
                this.enable_pay_button(validation_result.errors);
            }
        },

        create_order : function (method_id) {
            var form = $('#trustpayments-'+method_id).closest('form.trustpayments-payment-form');
            var self = this;
            $.ajax({
                type:       'POST',
                dataType:   'json',
                url:        form.attr('action'),
                data:       form.serialize() + '&methodId=' + method_id + '&cartHash=' + this.cartHash,
                success: function (response) {
                    if ( response.result === 'success' ) {
                            self.payment_methods[method_id].handler.submit();
                            return;
                    } else if (response.result ==='redirect') {
                        window.location.href = response.redirect;
                        return;
                    } else if ( response.result === 'failure' ) {
                        if (response.reload === 'true' ) {
                            window.location.href = window.location.href;
                            return;
                        } else if (response.redirect) {
                            window.location.href = response.redirect;
                            return;
                        }
                    }
                    self.enable_pay_button(trustpaymentsMsgJsonError);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    self.enable_pay_button(trustpaymentsMsgJsonError);
                }
            });


        },

        remove_existing_errors : function () {
            $("#notifications").empty();
        },

        show_new_errors : function (messages) {
            if ( typeof messages == 'undefined') {
                return;
            }
            $("#notifications").append('<div class="container"><article class="alert alert-danger" role="alert" data-alert="danger"><ul id="trustpayments-errors"></ul></article></div>');
            if (messages.constructor === Array) {
                for (var i = 0; i < messages.length; i++) {
                    $("#trustpayments-errors").append("<li>"+messages[i]+"</li>");
                }
            } else if (typeof messages == 'object') {
                for (var prop in messages) {
                    if (messages.hasOwnProperty(prop)) {
                        $("#trustpayments-errors").append("<li>"+messages[prop]+"</li>");
                    }
                }
            } else {
                $("#trustpayments-errors").append("<li>"+messages+"</li>");
            }
        },

        show_loading_spinner : function () {
            $("#checkout-payment-step").css({position:  "relative"});
            $("#checkout-payment-step").append('<div class="trustpayments-blocker" id="trustpayments-blocker"><div class="trustpayments-loader"></div></div>')
        },

        hide_loading_spinner : function () {
            $("#checkout-payment-step").css({position:  ""});
            $("#trustpayments-blocker").remove();
        }
    };

    trustpayments_checkout.init();

});
