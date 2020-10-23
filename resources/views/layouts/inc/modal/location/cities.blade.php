@if (isset($countryCode, $languageCode, $currSearch, $_token, $cities))
<div class="row">

	@foreach ($cities as $col)
		<div class="col-md-4">
			<ul class="list-link list-unstyled">
				@foreach ($col as $city)
					@if ($loop->parent->first and $loop->first)
						<?php
						$pathUri = $languageCode . '/' . t('v-search', ['countryCode' => strtolower($countryCode)], 'routes', $languageCode);
						$url = url($pathUri);
						?>
						<li><a href="{{ $url }}">{{ t('All Cities', [], 'global', $languageCode) }}</a></li>
					@endif
					
					<?php
					// Build URL
					if (currentLocaleShouldBeHiddenInUrl()) {
						$pathUri = t('v-search', ['countryCode' => strtolower($countryCode)], 'routes', $languageCode);
					} else {
						$pathUri = $languageCode . '/' . t('v-search', ['countryCode' => strtolower($countryCode)], 'routes', $languageCode);
					}
					$params = ['d' => config('country.icode'), 'l' => $city->id, '_token' => $_token];
					$url = qsUrl($pathUri, array_merge($currSearch, $params), null, false);
					?>
					<li><a href="{{ $url }}" title="{{ $city->name }}">{{ $city->name }}</a></li>
					
				@endforeach
			</ul>
		</div>
	@endforeach
	
</div>
@endif