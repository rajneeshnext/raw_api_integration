# Automating Paypal adaptive payment directly from the vendor area

add_action( 'dokan_refund_requested', 'vendor_refund_paypal_adaptive_API', 20 , 2);
function vendor_refund_paypal_adaptive_API($order_id, $refund) {
	 
	$order = wc_get_order( $order_id );
	$dokan_pap_key  = get_post_meta($order_id, '_dokan_pap_key', true);
	
	$refund_amount = $refund->get_refund_amount();
	$sellers = dokan_get_seller_id_by_order( $order_id );

    // check has sub order 
    if ( $order->get_meta('has_sub_order') ) {
        foreach ($sellers as $seller) {
            $seller_info      = get_userdata( $seller );
            $seller_email     = $seller_info->user_email;
        }
    } else {
        $seller_info      = get_userdata( $sellers );
        $seller_email     = $seller_info->user_email;
    }

    $vendor_data = get_user_meta( $refund->get_seller_id(), 'dokan_profile_settings', true );		
	$seller_email = isset( $vendor_data['payment']['paypal']['email'] ) ? esc_attr( $vendor_data['payment']['paypal']['email'] ) : '' ;

	if($seller_email!="" && $refund_amount>0){	

			$dokan_admin_percentage_type = get_user_meta( $refund->get_seller_id(), 'dokan_admin_percentage_type', true );
		    $dokan_admin_fee = get_user_meta( $refund->get_seller_id(), 'dokan_admin_percentage', true );
		    $refund_Admin_string ="";
		    if($dokan_admin_percentage_type == "percentage"){
		    	if($dokan_admin_fee>0){
		    		$dokan_admin_fee_amount = $dokan_admin_fee*$refund_amount/100;
		    		$refund_Admin_string = "&receiverList.receiver(1).amount=$dokan_admin_fee_amount&receiverList.receiver(1).email=sb-ilmth26738993@business.example.com";
		    		$refund_amount = $refund_amount - $dokan_admin_fee_amount;
		    	}else{
		    		//$receiverList.receiver(1).amount = 0;
		    	}
		    }else{
		    }


			$ch = curl_init();
			//_dokan_pap_key
			curl_setopt($ch, CURLOPT_URL, "https://svcs.sandbox.paypal.com/AdaptivePayments/Refund/");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "requestEnvelope.errorLanguage=en_US&payKey=$dokan_pap_key&receiverList.receiver(0).email=$seller_email&receiverList.receiver(0).amount=$refund_amount&currencyCode=USD$refund_Admin_string");
			curl_setopt($ch, CURLOPT_POST, 1);	

			$headers = array();
			$headers[] = "X-Paypal-Security-Userid:xxxxxxx.business.example.com";
			$headers[] = "X-Paypal-Security-Password:xxxxxxxxxxxxxx";
			$headers[] = "X-Paypal-Security-Signature:xxxxxxxxxx-w0rO2fusA-L4BFU5Ab6yRDpCmXL7JCZuDIEJIfA5VQZF";
			$headers[] = "X-Paypal-Request-Data-Format:NV";
			$headers[] = "X-Paypal-Response-Data-Format:JSON";
			$headers[] = "X-Paypal-Application-Id:APP-xxxxxxxxx";
			$headers[] = "Content-Type:text/value";
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$result = curl_exec($ch);
			

			$my_array_data = json_decode($result, TRUE);
			//print_r($my_array_data);

			if (curl_errno($ch) || $my_array_data['responseEnvelope']['ack'] == "Failure") {
			    $err =  'Error:' . $my_array_data['error'][0]['message'];
			    $order->add_order_note(
			        sprintf(
			            /* translators: 1) user name */
			            __( 'Refund by Vendor (%2$s) - Admin Fees %3$s - Amount: %1$s - RefundStatus - Failed %4$s', 'dokan' ),
			            $refund->get_refund_amount(), $refund->get_seller_id(), $dokan_admin_fee, $err 
			        )
			    );
			}else{
				//echo "<pre>";
				
				$refundStatus = $my_array_data['refundInfoList']['refundInfo'][0]['refundStatus'];

				$order->add_order_note(
			        sprintf(
			            /* translators: 1) user name */
			            __( 'Refund by Vendor (%2$s) - Admin Fees %3$s - Amount: %1$s - RefundStatus - %4$s', 'dokan' ),
			            $refund->get_refund_amount(), $refund->get_seller_id(), $dokan_admin_fee, $refundStatus 
			        )
			    );

				$refund->approve(); 
			}
			
	}
}
