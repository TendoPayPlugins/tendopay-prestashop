/**
* 2007-2021 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

$(document).ready(function() {

	 var checkmode = $('#TENDOPAY_LIVE_MODE option:selected').val();

	   if(checkmode !='undefined' && checkmode ==1) {

	   	//$('form#module_form .form-wrapper .form-group:nth-child(6)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(7)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(8)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(9)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(10)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(11)').css('display','none');

	   } else if(checkmode !='undefined' && checkmode ==0) {

	   	//$('form#module_form .form-wrapper .form-group:nth-child(1)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(2)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(3)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(4)').css('display','none');
	   	// $('form#module_form .form-wrapper .form-group:nth-child(5)').css('display','none');
	   	// $('form#module_form .form-wrapper .form-group:nth-child(6)').css('display','none');

	   }

	   $(document).on('change', '#TENDOPAY_LIVE_MODE', function() {
	   	//$('#TENDOPAY_LIVE_MODE').on('change', function() {

	   	var changemode =$(this).val();

	   	if(changemode !='undefined' && changemode ==1) {

	   	
	   	$('form#module_form .form-wrapper .form-group:nth-child(7)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(8)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(9)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(10)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(11)').css('display','none');

	   //	$('form#module_form .form-wrapper .form-group:nth-child(1)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(2)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(3)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(4)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(5)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(6)').css('display','none');

	   } else if(changemode !='undefined' && changemode ==0) {

	   	//$('form#module_form .form-wrapper .form-group:nth-child(1)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(2)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(3)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(4)').css('display','none');
	   	$('form#module_form .form-wrapper .form-group:nth-child(5)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(6)').css('display','block');


	   	//$('form#module_form .form-wrapper .form-group:nth-child(6)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(7)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(8)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(9)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(10)').css('display','block');
	   	$('form#module_form .form-wrapper .form-group:nth-child(11)').css('display','block');

	   }


	   });

});
