<div class = "current-batch">	
	<div id = "background">
		@if ($batch->store)
			<p id="bg-text">RUSH</p>
		@endif
	</div>
	<div id = "content">
	<!-- table width = "100%" cellpadding = "2" cellspacing = "2" border = "0"-->
	<table style = "width:195mm;" cellpadding = "0" cellspacing = "0" border = "0">
		<tr valign = "top">
			<td colspan = "10">
				<table width = "100%" cellpadding = "0" cellspacing = "0" border = "0">
					<tr valign = "top">
						<td colspan=2>
						@if ($batch->store)
							<strong>{{ $batch->store->store_name }}</strong>
						@else
							&nbsp;
						@endif
						</td>
						<td colspan="2" rowspan="5" align="center">
							<table width="100%" cellpadding = "0" cellspacing = "0" border = "1">
								<tr>
									<th>Stock#</th>
									<th>Description</th>
									<th>Bin</th>
									<th>Qty</th>
								</tr>
								@if($inventory != NULL)
									@foreach ($inventory as $row)
										<tr>
											@if (isset($stock[$row->stock_no_unique]))
												<td align="center">{{$row->stock_no_unique}}</td>
												<td align="center">{{$row->stock_name_discription}}</td>
												<td align="center">{{$row->wh_bin}}</td>
												<td align="center">{{$stock[$row->stock_no_unique]}}</td>
											@else
												<td colspan="4" align="center">To Be Assigned</td>
											@endif
										</tr>
									@endforeach
								@else 
									<tr>
										<td colspan="4" align="center">No Inventory Information Available</td>
									</tr>
								@endif
							</table>
							<strong style="font-size: 160%;">{{ $batch->route->summary_msg_1 }}</strong>
							<strong  style="font-size: 110%;">{{ $batch->route->summary_msg_2 }}</strong>
						</td>
						<td rowspan = "5" align = "right">
								<div align="center">
									{!! \Monogram\Helper::getHtmlBarcode(sprintf("%s", $batch_number)) !!}
									<br>
									{{ $batch->batch_number }}
								</div>
						</td>
					</tr>
					<tr>
						<td align = "left" width = "150 ">Batch #</td>
						<td>{{$batch_number}}</td>
					</tr>
					<tr>
						<td align = "left">First Order date</td>
						<td>{{substr($batch->min_order_date, 0, 10)}}</td>
					</tr>
					<tr rowspan=2>
						<td colspan=2>&nbsp;</td>
					</tr>
					
					{{--
					<tr>
						<td align = "left">Status</td>
						<td>{{ $batch->status }}</td>
					</tr>
					<tr valign = "middle">
						<td align = "left">Current station</td>
						<td align = "left">{{$batch->station->station_name}}</td>
					</tr>
					<tr>
						<td align = "left">Next station</td>
						<td>{{ $next_station_name }}</td>
					</tr> 
					--}}
					<tr>
						<td align = "left">Route</td>
						<td colspan = "5">{{ $batch->route->batch_code }} / {{ $batch->route->batch_route_name }} => {!! $stations !!}</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td>
				<table width="100%" cellpadding = "0" cellspacing = "1" border = "0">
					<tr>
						<td colspan="7">
							<hr size = "1" />
						</td>
					</tr>
					<tr valign = "top">
						<th align = "left" style = "width:15mm;">Item #</th>
						<th align = "center" style = "width:15mm;">Order</th>
						<th align = "center" style = "width:20mm;">Date</th>
						<th align = "center" style = "width:5mm;">Qty</th>
						<th align = "center" style = "width:40mm;">SKU</th>
						<th align = "left" style = "width:40mm;">Item name</th>
						<th align = "left" style = "width:60mm;">Options</th>
					</tr>
					<tr>
						<td colspan="7">
							<hr size = "1" />
						</td>
					</tr>
					@setvar($count = 0)
					@foreach($batch->items as $row)
							@if(!$row->tracking_number)
								@setvar($message = '')
								@if (strpos(strtolower($row->item_option), 'add_inspirational_message'))
									@setvar($options = json_decode($row->item_option, true))
									@foreach($options as $key=>$value)
										@if (strpos(strtolower($key), 'add_inspirational_message'))
											@setvar($message = 'ON BACK: ' . $value) 
										@endif
									@endforeach
								@endif
								@setvar($count = $count + $row->item_quantity)
								<tr valign = "top" class="nobreak">
									<td align = "left" valign = "top">{{ $row->id }}</td>
									<td align = "center" valign = "top">{{$row->order->short_order}}</td>
									<td align = "left">{{substr($row->order->order_date, 0, 10)}}</td>
									<td align = "center">{{$row->item_quantity}}</td>
									<td align = "center" rowspan="2">{{$row->child_sku}}</td>
									<td align = "left" rowspan="2">{{$row->item_description}}</td>
									<td align = "left" rowspan="2">{!! \Monogram\Helper::jsonTransformer($row->item_option, "<br/>", 1) !!}</td>
								</tr>
								<tr class="nobreak">
										<td height="30"></td>
										<td colspan ="2" valign="bottom">
											@foreach ($row->inventoryunit as $unit)
												@if (!empty($unit->inventory))
													<strong>Stock # {{$unit->stock_no_unique}}</strong>
												@endif
											@endforeach
										</td>
										<td colspan="2">	
											<h3>{{ $message }}</h3>
										</td>
								</tr>
								<tr>
									<td colspan="7">
										<br />
										<hr size = "1" />
									</td>
								</tr>
							@endif
					@endforeach
					<tr valign = "top">
						<td colspan = "4" align = "left"><strong>{{ $count }} Items in Batch </strong></td>
						<td align = "right"><strong>Batch # {{ $batch_number }} </strong></td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	</div>
</div>