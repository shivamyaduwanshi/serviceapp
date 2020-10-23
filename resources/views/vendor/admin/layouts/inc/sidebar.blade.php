@if (auth()->check())
	<?php
	// Get plugins admin menu
	$pluginsMenu = '';
	$plugins = plugin_installed_list();
	if (!empty($plugins)) {
		foreach($plugins as $plugin) {
			if (method_exists($plugin->class, 'getAdminMenu')) {
				$pluginsMenu .= call_user_func($plugin->class . '::getAdminMenu');
			}
		}
	}
	?>
	<style>
		#adminSidebar ul li span {
			text-transform: capitalize;
		}
	</style>
	<aside class="left-sidebar" id="adminSidebar">
		{{-- Sidebar scroll --}}
		<div class="scroll-sidebar">
			{{-- Sidebar navigation --}}
			<nav class="sidebar-nav">
				<ul id="sidebarnav">
					<li class="sidebar-item user-profile">
						<a class="sidebar-link has-arrow waves-effect waves-dark" href="javascript:void(0)" aria-expanded="false">
							<img src="{{ Gravatar::get(auth()->user()->email) }}" alt="user">
							<span class="hide-menu">{{ auth()->user()->name }}</span>
						</a>
						<ul aria-expanded="false" class="collapse first-level">
							<li class="sidebar-item">
								<a href="{{ admin_url('account') }}" class="sidebar-link p-0">
									<i class="mdi mdi-adjust"></i>
									<span class="hide-menu">{{ trans('admin.my_account') }}</span>
								</a>
							</li>
							<li class="sidebar-item">
								<a href="{{ admin_url('logout') }}" class="sidebar-link p-0">
									<i class="mdi mdi-adjust"></i>
									<span class="hide-menu">{{ trans('admin.logout') }}</span>
								</a>
							</li>
						</ul>
					</li>
					
					<li class="sidebar-item">
						<a href="{{ admin_url('dashboard') }}" class="sidebar-link waves-effect waves-dark">
							<i data-feather="home" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.dashboard') }}</span>
						</a>
					</li>
					@if (
						auth()->user()->can('list-post') ||
						auth()->user()->can('list-category') ||
						auth()->user()->can('list-picture') ||
						auth()->user()->can('list-post-type') ||
						auth()->user()->can('list-field') ||
						userHasSuperAdminPermissions()
					)
						<li class="sidebar-item">
							<a href="#" class="sidebar-link has-arrow waves-effect waves-dark">
								<i data-feather="list"></i> <span class="hide-menu">{{ trans('admin.ads') }}</span>
							</a>
							<ul aria-expanded="false" class="collapse first-level">
								@if (auth()->user()->can('list-post') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('posts') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.list') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-category') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('categories') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.categories') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-picture') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('pictures') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.pictures') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-post-type') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('p_types') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.ad types') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-field') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('custom_fields') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.custom fields') }}</span>
										</a>
									</li>
								@endif
							</ul>
						</li>
					@endif
					
					@if (
						auth()->user()->can('list-user') ||
						auth()->user()->can('list-role') ||
						auth()->user()->can('list-permission') ||
						auth()->user()->can('list-gender') ||
						userHasSuperAdminPermissions()
					)
						<li  class="sidebar-item">
							<a href="#" class="sidebar-link has-arrow waves-effect waves-dark">
								<i data-feather="users" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.users') }}</span>
							</a>
							<ul aria-expanded="false" class="collapse first-level">
								@if (auth()->user()->can('list-user') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('users') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.list') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-role') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('roles') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.roles') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-permission') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('permissions') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.permissions') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-gender') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('genders') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.titles') }}</span>
										</a>
									</li>
								@endif
							</ul>
						</li>
					@endif
					
					@if (auth()->user()->can('list-payment') || userHasSuperAdminPermissions())
						<li class="sidebar-item">
							<a href="{{ admin_url('payments') }}" class="sidebar-link">
								<i data-feather="dollar-sign" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.payments') }}</span>
							</a>
						</li>
					@endif
					@if (auth()->user()->can('list-page') || userHasSuperAdminPermissions())
						<li class="sidebar-item">
							<a href="{{ admin_url('pages') }}" class="sidebar-link">
								<i data-feather="book-open" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.pages') }}</span>
							</a>
						</li>
					@endif
					{!! $pluginsMenu !!}
					
					{{-- ======================================= --}}
					@if (
						auth()->user()->can('list-setting') ||
						auth()->user()->can('list-home-section') ||
						auth()->user()->can('list-language') ||
						auth()->user()->can('list-meta-tag') ||
						auth()->user()->can('list-package') ||
						auth()->user()->can('list-payment-method') ||
						auth()->user()->can('list-advertising') ||
						auth()->user()->can('list-country') ||
						auth()->user()->can('list-currency') ||
						auth()->user()->can('list-time-zone') ||
						auth()->user()->can('list-blacklist') ||
						auth()->user()->can('list-report-type') ||
						userHasSuperAdminPermissions()
					)
						<li class="nav-small-cap">
							<i class="mdi mdi-dots-horizontal"></i>
							<span class="hide-menu">{{ trans('admin.configuration') }}</span>
						</li>
						
						<li  class="sidebar-item">
							<a href="#" class="sidebar-link has-arrow waves-effect waves-dark">
								<i data-feather="settings" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.setup') }}</span>
							</a>
							<ul aria-expanded="false" class="collapse first-level">
								@if (auth()->user()->can('list-setting') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('settings') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.general settings') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-home-section') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('homepage') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.homepage') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-language') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('languages') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.languages') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-meta-tag') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('meta_tags') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.meta tags') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-package') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('packages') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.packages') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-payment-method') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('payment_methods') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.payment methods') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-advertising') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('advertisings') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.advertising') }}</span>
										</a>
									</li>
								@endif
								@if (
									auth()->user()->can('list-country') ||
									auth()->user()->can('list-currency') ||
									auth()->user()->can('list-time-zone') ||
									userHasSuperAdminPermissions()
								)
									<li class="sidebar-item">
										<a href="#" class="has-arrow sidebar-link">
											<i class="fa fa-globe"></i> <span class="hide-menu">{{ trans('admin.international') }}</span>
										</a>
										<ul aria-expanded="false" class="collapse second-level">
											@if (auth()->user()->can('list-country') || userHasSuperAdminPermissions())
												<li class="sidebar-item">
													<a href="{{ admin_url('countries') }}" class="sidebar-link">
														<i class="mdi mdi-adjust"></i>
														<span class="hide-menu">{{ trans('admin.countries') }}</span>
													</a>
												</li>
											@endif
											@if (auth()->user()->can('list-currency') || userHasSuperAdminPermissions())
												<li class="sidebar-item">
													<a href="{{ admin_url('currencies') }}" class="sidebar-link">
														<i class="mdi mdi-adjust"></i>
														<span class="hide-menu">{{ trans('admin.currencies') }}</span>
													</a>
												</li>
											@endif
											@if (auth()->user()->can('list-time-zone') || userHasSuperAdminPermissions())
												<li class="sidebar-item">
													<a href="{{ admin_url('time_zones') }}" class="sidebar-link">
														<i class="mdi mdi-adjust"></i>
														<span class="hide-menu">{{ trans('admin.time zones') }}</span>
													</a>
												</li>
											@endif
										</ul>
									</li>
								@endif
								@if (auth()->user()->can('list-blacklist') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('blacklists') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.blacklist') }}</span>
										</a>
									</li>
								@endif
								@if (auth()->user()->can('list-report-type') || userHasSuperAdminPermissions())
									<li class="sidebar-item">
										<a href="{{ admin_url('report_types') }}" class="sidebar-link">
											<i class="mdi mdi-adjust"></i>
											<span class="hide-menu">{{ trans('admin.report types') }}</span>
										</a>
									</li>
								@endif
							</ul>
						</li>
					@endif
					
					@if (auth()->user()->can('list-plugin') || userHasSuperAdminPermissions())
						<li class="sidebar-item">
							<a href="{{ admin_url('plugins') }}" class="sidebar-link">
								<i data-feather="package" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.plugins') }}</span>
							</a>
						</li>
					@endif
					@if (auth()->user()->can('clear-cache') || userHasSuperAdminPermissions())
						<li class="sidebar-item">
							<a href="{{ admin_url('actions/clear_cache') }}" class="sidebar-link">
								<i data-feather="refresh-cw" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.clear cache') }}</span>
							</a>
						</li>
					@endif
					@if (auth()->user()->can('list-backup') || userHasSuperAdminPermissions())
						<li class="sidebar-item">
							<a href="{{ admin_url('backups') }}" class="sidebar-link">
								<i data-feather="hard-drive" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.backups') }}</span>
							</a>
						</li>
					@endif
					
					@if (
						auth()->user()->can('maintenance-up') ||
						auth()->user()->can('maintenance-down') ||
						userHasSuperAdminPermissions()
					)
						@if (app()->isDownForMaintenance())
							@if (auth()->user()->can('maintenance-up') || userHasSuperAdminPermissions())
								<li class="sidebar-item">
									<a href="{{ admin_url('actions/maintenance_up') }}"
									   data-toggle="tooltip"
									   title="{{ trans('admin.Leave Maintenance Mode') }}"
									   class="sidebar-link"
									>
										<i data-feather="globe" class="feather-icon"></i> <span class="hide-menu">{{ trans('admin.Live Mode') }}</span>
									</a>
								</li>
							@endif
						@else
							@if (auth()->user()->can('maintenance-down') || userHasSuperAdminPermissions())
								<li class="sidebar-item">
									<a href="#"
									   data-toggle="modal"
									   data-target="#maintenanceMode"
									   title="{{ trans('admin.Put in Maintenance Mode') }}"
									   class="sidebar-link"
									>
										<i data-feather="alert-circle"></i> <span class="hide-menu">{{ trans('admin.Maintenance') }}</span>
									</a>
								</li>
							@endif
						@endif
					@endif
					
				</ul>
			</nav>
			
		</div>
		
	</aside>
@endif
