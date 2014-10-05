<div class="menu-active overlay">
<div id="wrapper">
	<div class="editcartmodal webstore-modal webstore-modal-overlay webstore-modal-cart webstore-overlay webstore-overlay-cart" id="cart">
		<section id="cart">
			<header class="overlay">
				<h1>
					<?php
						echo
							CHtml::link(
								CHtml::image(Yii::app()->params['HEADER_IMAGE']).
								CHtml::tag('span', array(), Yii::app()->params['STORE_NAME']),
								Yii::app()->createUrl("site/index"),
								array('class' => 'logo-placement')
							);
					?>
				</h1>
				<?php echo CHtml::htmlButton(Yii::t('cart', 'Continue Shopping'), array('class'=>'exit'));?>
				<a href="#" class="edit button inset" onclick="$('table.lines').toggleClass('edit'); return false;">Edit</a>
			</header>
			<article class="section-inner">
					<h1><?php echo Yii::t('cart','Shopping Cart'); ?></h1>
					<?php $this->widget(
						'zii.widgets.grid.CGridView', array(
							'htmlOptions' => array('class' => 'lines lines-container'),
							'id' => 'user-grid',
							'itemsCssClass' => 'lines',
							'dataProvider' => Yii::app()->shoppingcart->dataProvider,
							'summaryText' => '',
							'columns' => array(
								array(
									'name' => 'image',
									'header' => Yii::t('cart', 'Product'),
									'headerHtmlOptions' => array('class' => 'description', 'colspan' => 2),
									'type' => 'raw',
									'value' => 'CHtml::image(Images::GetLink($data -> product -> image_id,ImagesType::slider)).CHtml::tag("td",array("class" => "description"),CHtml::link("<strong>".$data -> product->title."</strong>", $data -> product->Link, array()))',
									'htmlOptions' => array(
										'class' => 'image'
									),
								),
								array(
									'name' => 'sell',
									'header' => Yii::t('cart', 'Price'),
									'headerHtmlOptions' => array('class' => 'price'),
									'type' => 'raw',
									'value' => 'wseditcartmodal::renderSellPrice($data)',
									'htmlOptions' => array(
										'class' => 'price'
									),
								),
								array(
									'name' => 'qty',
									'header' => Yii::t('cart', 'Qty.'),
									'sortable' => false,
									'headerHtmlOptions' => array('class' => 'quantity'),
									'type' => 'raw',
									'value' => 'CHtml::numberField("CartItem_qty[$data->id]",$data->qty,array(
		                                        "data-pk"=>$data->id,
		                                        "onchange" =>"updateCart(this)",
		                                    ))',
									'htmlOptions' => array(
										'class' => 'quantity'
									),
								),
								array(
									'name' => 'sell_total',
									'header' => Yii::t('cart', 'Total'),
									'sortable' => false,
									'headerHtmlOptions' => array('class' => 'subtotal'),
									'type' => 'raw',
									'value' => '$data->sellTotalFormatted',
									'htmlOptions' => array(
										'class' => 'subtotal'
									),
								),
								array(
									'name' => 'remove',
									'header' => '',
									'headerHtmlOptions' => array('class' => 'remove'),
									'htmlOptions' => array(
										'class' => 'remove'
									),
									'type' => 'raw',
									'value' => 'CHtml::link("×","#",array(
										"data-pk"=>$data->id,"class"=>"remove", "onclick"=>"removeItem(this)")
										)',

								),

							),
							'rowHtmlOptionsExpression' => 'array("id" => "cart_row_".$data->id)',
						)
					);
					?>
					<div class="cart-footer">
						<form class="promo">
							<div style="position:relative;">
								<?php
								echo CHtml::textField(
									CHtml::activeId('EditCart','promoCode'),
									(Yii::app()->shoppingcart->promoCode !== null ? Yii::app()->shoppingcart->promoCode : ''),
									array(
										'placeholder' => Yii::t('cart','Promo Code'),
										'class' => "",
										'onkeypress' => 'return wseditcartmodal.ajaxTogglePromoCodeEnterKey(event, ' .
											json_encode(CHtml::activeId('EditCart','promoCode')) .
											');',
										'readonly' => Yii::app()->shoppingcart->promoCode !== null
									)
								);
								echo CHtml::htmlButton (
									Yii::app()->shoppingcart->promoCode !== null ? Yii::t('cart', 'Remove') : Yii::t('cart', 'Apply'),
									array(
										'type' => 'button',
										'class' => 'inset promocode-apply' . (Yii::app()->shoppingcart->promoCode !== null ? ' promocode-applied' : ''),
										'onclick' => 'wseditcartmodal.ajaxTogglePromoCode(' .
											json_encode(CHtml::activeId('EditCart', 'promoCode')) .
											');'
									)
								);
								?>
							</div>
							<?php
							echo CHtml::tag(
								'div',
								array(
									'id' => CHtml::activeId('EditCart','promoCode') . '_em_',
									'class' => 'form-error',
									'style' => 'display: none'
								),
								'<p>&nbsp;</p>'
							);
							?>
							<p class="description"><?php echo Yii::t('cart','Specials, promotional offers and discounts') ?></p>
						</form>
						<div class="totals">
							<?php $this->widget('ext.wsshippingestimator.WsShippingEstimatorTooltip'); ?>

							<table>
								<tbody>
									<tr id="PromoCodeLine" class="<?php echo Yii::app()->shoppingcart->promoCode ? 'webstore-promo-line' : 'webstore-promo-line hide-me';?>" >
										<th colspan='2'>
											<?php echo Yii::t('cart','Promo & Discounts')." <td id=\"PromoCodeStr\">" . Yii::app()->shoppingcart->totalDiscountFormatted; ?></td>
										</th>
									</tr>
									<tr class="subtotal">
										<th colspan='2'><?php echo Yii::t('cart','Subtotal'); ?></th>
										<td id="CartSubtotal" class="subtotal"><?php echo _xls_currency(Yii::app()->shoppingcart->subtotal) ?></td>
									</tr>
									<?php $this->widget('ext.wsshippingestimator.WsShippingEstimator'); ?>
								</tbody>
								<tfoot>
								<tr class="total">
									<th colspan='2'><?php echo Yii::t('cart','Total'); ?></th>
									<td id="CartTotal" class="wsshippingestimator-total-estimate subtotal"><?php echo _xls_currency(Yii::app()->shoppingcart->total); ?></td>
								</tr>
								</tfoot>
							</table>
						</div>
						<div class="submit">
							<?php
							echo CHtml::htmlButton(Yii::t('cart', 'Checkout'), array('class'=>'checkout','onClick'=>"window.location.href="."'".Yii::app()->controller->createUrl('/checkout')."'"));
							echo CHtml::htmlButton(Yii::t('cart', 'Continue Shopping'), array('class'=>'continue'));
							?>
					</div>
				</div>
			</article>
			<footer>
				<p>
					<?php
					if (Yii::app()->params['ENABLE_SSL'] == 1)
					{
						echo
							CHtml::image(
								'/images/lock.png',
								'lock image ',
								array(
									'height'=> 14
								)
							).
							CHtml::tag('strong',array(),'Safe &amp; Secure ').Yii::t('cart','Bank-grade SSL encryption protects your purchase.');
					}

					$objPrivacy = CustomPage::LoadByKey('privacy');
					if ($objPrivacy instanceof CustomPage && $objPrivacy->tab_position !== 0)
					{
						echo
							CHtml::link(
								Yii::t('cart', 'Privacy Policy'),
								$objPrivacy->Link,
								array('target' => '_blank')
	                        );
					}
					?>
				</p>
			</footer>
		</section>
	</div>
	</div>
</div>
