<?php
// Redirect if directly accessed without authenticated session
if ((!isset($_SESSION['loginUsername'])) || ((isset($_SESSION['loginUsername'])) && ($_SESSION['userLevel'] > 0))) {
    $redirect = "../../403.php";
    $redirect_go_to = sprintf("Location: %s", $redirect);
    header($redirect_go_to);
    exit();
}

$query_coupons = sprintf(
	"SELECT c.id, c.code, c.credits_granted, c.max_redemptions, c.redeemed_count, c.expires_at, c.is_active, c.created_at, c.updated_at, MAX(r.redeemed_at) AS last_redeemed_at
	 FROM %s c
	 LEFT JOIN %s r ON c.id = r.coupon_code_id
	 GROUP BY c.id
	 ORDER BY c.created_at DESC",
	$prefix."coupon_codes",
	$prefix."coupon_redemptions"
);
$coupons = mysqli_query($connection,$query_coupons) or die (mysqli_error($connection));
$row_coupons = mysqli_fetch_assoc($coupons);
$totalRows_coupons = mysqli_num_rows($coupons);

$query_coupon_stats = sprintf(
	"SELECT
		COUNT(*) AS total_codes,
		SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_codes,
		IFNULL(SUM(redeemed_count),0) AS redeemed_total
	 FROM %s",
	$prefix."coupon_codes"
);
$coupon_stats = mysqli_query($connection,$query_coupon_stats) or die (mysqli_error($connection));
$row_coupon_stats = mysqli_fetch_assoc($coupon_stats);

$query_redemptions = sprintf(
	"SELECT r.id, c.code, r.credits_granted, r.redeemed_at, u.user_name, b.brewerFirstName, b.brewerLastName,
	        (SELECT COUNT(*) FROM %s ep WHERE ep.redemption_id = r.id) AS credits_used
	 FROM %s r
	 LEFT JOIN %s c ON r.coupon_code_id = c.id
	 LEFT JOIN %s u ON r.user_id = u.id
	 LEFT JOIN %s b ON b.uid = u.id
	 ORDER BY r.redeemed_at DESC",
	$prefix."coupon_entry_payments",
	$prefix."coupon_redemptions",
	$prefix."coupon_codes",
	$prefix."users",
	$prefix."brewer"
);
$coupon_redemptions = mysqli_query($connection,$query_redemptions) or die (mysqli_error($connection));
$row_coupon_redemptions = mysqli_fetch_assoc($coupon_redemptions);
$totalRows_coupon_redemptions = mysqli_num_rows($coupon_redemptions);
?>

<script type="text/javascript" language="javascript">
	$(document).ready(function() {
		$('#sortable-coupons').dataTable({
			"bPaginate" : true,
			"sPaginationType" : "full_numbers",
			"bLengthChange" : true,
			"iDisplayLength" : <?php echo $limit; ?>,
			"sDom": 'fprtp',
			"bStateSave" : false,
			"aaSorting": [[8,'desc']],
			"aoColumns": [
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				{ "asSorting": [ ] }
			]
		});

		$('#sortable-redemptions').dataTable({
			"bPaginate" : true,
			"sPaginationType" : "full_numbers",
			"bLengthChange" : true,
			"iDisplayLength" : <?php echo $limit; ?>,
			"sDom": 'fprtp',
			"bStateSave" : false,
			"aaSorting": [[4,'desc']],
			"aoColumns": [
				null,
				null,
				null,
				null,
				null
			]
		});
	});
</script>

<p class="lead"><?php echo $_SESSION['contestName']; ?>: Manage Vouchers</p>

<div class="bcoem-admin-element hidden-print">
<dl class="dl-horizontal">
	<dt>Total Codes</dt><dd><?php echo intval($row_coupon_stats['total_codes']); ?></dd>
	<dt>Active Codes</dt><dd><?php echo intval($row_coupon_stats['active_codes']); ?></dd>
	<dt>Total Redemptions</dt><dd><?php echo intval($row_coupon_stats['redeemed_total']); ?></dd>
</dl>
</div>

<div class="bcoem-admin-element hidden-print">
<h4>Create Voucher Code</h4>
<form data-toggle="validator" role="form" class="form-horizontal" method="post" action="<?php echo $base_url; ?>includes/process.inc.php?section=admin&amp;go=coupons&amp;action=coupon_add">
	<input type="hidden" name="user_session_token" value ="<?php if (isset($_SESSION['user_session_token'])) echo htmlspecialchars($_SESSION['user_session_token'], ENT_QUOTES, 'UTF-8'); ?>">
	<div class="form-group">
		<label for="couponCode" class="col-lg-2 col-md-3 col-sm-4 col-xs-12 control-label"><?php echo $label_coupon_code; ?></label>
		<div class="col-lg-4 col-md-5 col-sm-8 col-xs-12">
			<input class="form-control" id="couponCode" name="couponCode" type="text" maxlength="64" required>
		</div>
	</div>
	<div class="form-group">
		<label for="creditsGranted" class="col-lg-2 col-md-3 col-sm-4 col-xs-12 control-label"><?php echo $label_coupon_credits; ?></label>
		<div class="col-lg-2 col-md-2 col-sm-4 col-xs-12">
			<input class="form-control" id="creditsGranted" name="creditsGranted" type="number" min="1" step="1" value="1" required>
		</div>
	</div>
	<div class="form-group">
		<label for="maxRedemptions" class="col-lg-2 col-md-3 col-sm-4 col-xs-12 control-label">Max Redemptions</label>
		<div class="col-lg-2 col-md-2 col-sm-4 col-xs-12">
			<input class="form-control" id="maxRedemptions" name="maxRedemptions" type="number" min="1" step="1" value="1">
		</div>
		<p class="col-lg-4 col-md-4 col-sm-8 col-xs-12 help-block">Clear the value for unlimited redemptions.</p>
	</div>
	<div class="form-group">
		<label for="expiresAt" class="col-lg-2 col-md-3 col-sm-4 col-xs-12 control-label">Expires At</label>
		<div class="col-lg-3 col-md-4 col-sm-6 col-xs-12">
			<input class="form-control" id="expiresAt" name="expiresAt" type="datetime-local">
		</div>
		<p class="col-lg-4 col-md-4 col-sm-8 col-xs-12 help-block">Leave blank for no expiration.</p>
	</div>
	<div class="form-group">
		<div class="col-lg-offset-2 col-md-offset-3 col-sm-offset-4 col-xs-12">
			<input type="submit" class="btn btn-primary" value="Add Voucher">
		</div>
	</div>
</form>
</div>

<div class="bcoem-admin-element hidden-print">
<h4>Import Voucher Codes</h4>
<p>Download the CSV template, fill in your voucher codes, then upload the completed file below.</p>
<div class="btn-group" role="group" aria-label="download-template">
	<a class="btn btn-default hide-loader" href="<?php echo $base_url; ?>templates/coupon-import-template.csv" download><span class="fa fa-download"></span> Download CSV Template</a>
</div>
<br><br>
<form data-toggle="validator" role="form" class="form-horizontal" method="post" enctype="multipart/form-data" action="<?php echo $base_url; ?>includes/process.inc.php?section=admin&amp;go=coupons&amp;action=coupon_import">
	<input type="hidden" name="user_session_token" value ="<?php if (isset($_SESSION['user_session_token'])) echo htmlspecialchars($_SESSION['user_session_token'], ENT_QUOTES, 'UTF-8'); ?>">
	<div class="form-group">
		<label for="couponImportFile" class="col-lg-2 col-md-3 col-sm-4 col-xs-12 control-label">Upload CSV</label>
		<div class="col-lg-6 col-md-6 col-sm-8 col-xs-12">
			<input class="form-control" id="couponImportFile" name="couponImportFile" type="file" accept=".csv,text/csv" required>
			<span class="help-block">Template columns: code, credits_granted, max_redemptions, expires_at, is_active</span>
		</div>
	</div>
	<div class="form-group">
		<div class="col-lg-offset-2 col-md-offset-3 col-sm-offset-4 col-xs-12">
			<input type="submit" class="btn btn-primary" value="Import Vouchers">
		</div>
	</div>
</form>
</div>

<h3>Codes</h3>
<?php if ($totalRows_coupons > 0) { ?>
<table class="table table-responsive table-striped table-bordered" id="sortable-coupons">
	<thead>
		<tr>
			<th><?php echo $label_coupon_code; ?></th>
			<th>Status</th>
			<th><?php echo $label_coupon_credits; ?></th>
			<th>Redeemed</th>
			<th>Max</th>
			<th>Remaining</th>
			<th>Expires</th>
			<th>Last Redeemed</th>
			<th>Created</th>
			<th><?php echo $label_actions; ?></th>
		</tr>
	</thead>
	<tbody>
	<?php do {
		$remaining_redemptions = "Unlimited";
		if (!empty($row_coupons['max_redemptions'])) {
			$remaining_redemptions = intval($row_coupons['max_redemptions']) - intval($row_coupons['redeemed_count']);
			if ($remaining_redemptions < 0) $remaining_redemptions = 0;
		}
		$status_display = "<span class=\"text-success\">Active</span>";
		$toggle_to = 0;
		$toggle_title = "Disable Voucher";
		$toggle_icon = "fa-toggle-on";
		if (intval($row_coupons['is_active']) !== 1) {
			$status_display = "<span class=\"text-danger\">Inactive</span>";
			$toggle_to = 1;
			$toggle_title = "Enable Voucher";
			$toggle_icon = "fa-toggle-off";
		}
	?>
		<tr>
			<td><strong><?php echo htmlspecialchars($row_coupons['code']); ?></strong></td>
			<td><?php echo $status_display; ?></td>
			<td><?php echo intval($row_coupons['credits_granted']); ?></td>
			<td><?php echo intval($row_coupons['redeemed_count']); ?></td>
			<td><?php if (!empty($row_coupons['max_redemptions'])) echo intval($row_coupons['max_redemptions']); else echo "Unlimited"; ?></td>
			<td><?php echo $remaining_redemptions; ?></td>
			<td><?php if (!empty($row_coupons['expires_at'])) echo getTimeZoneDateTime($_SESSION['prefsTimeZone'], strtotime($row_coupons['expires_at']), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time-no-gmt"); else echo "Never"; ?></td>
			<td><?php if (!empty($row_coupons['last_redeemed_at'])) echo getTimeZoneDateTime($_SESSION['prefsTimeZone'], strtotime($row_coupons['last_redeemed_at']), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time-no-gmt"); else echo "&mdash;"; ?></td>
			<td><?php echo getTimeZoneDateTime($_SESSION['prefsTimeZone'], strtotime($row_coupons['created_at']), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time-no-gmt"); ?></td>
			<td nowrap>
				<a class="hide-loader" href="<?php echo $base_url; ?>includes/process.inc.php?section=admin&amp;go=coupons&amp;action=coupon_toggle&amp;id=<?php echo intval($row_coupons['id']); ?>&amp;view=<?php echo $toggle_to; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $toggle_title; ?>"><i class="fa fa-lg <?php echo $toggle_icon; ?>"></i></a>
			</td>
		</tr>
	<?php } while ($row_coupons = mysqli_fetch_assoc($coupons)); ?>
	</tbody>
</table>
<?php } else { ?>
<p>No voucher codes found.</p>
<?php } ?>

<h3 class="mt-4">Redemption Report</h3>
<?php if ($totalRows_coupon_redemptions > 0) { ?>
<table class="table table-responsive table-striped table-bordered" id="sortable-redemptions">
	<thead>
		<tr>
			<th><?php echo $label_coupon_code; ?></th>
			<th>User</th>
			<th><?php echo $label_coupon_credits; ?></th>
			<th>Credits Used</th>
			<th><?php echo $label_date; ?></th>
		</tr>
	</thead>
	<tbody>
		<?php do {
			$user_display = $row_coupon_redemptions['user_name'];
			if ((!empty($row_coupon_redemptions['brewerFirstName'])) || (!empty($row_coupon_redemptions['brewerLastName']))) {
				$user_display .= "<br><small>".trim($row_coupon_redemptions['brewerFirstName']." ".$row_coupon_redemptions['brewerLastName'])."</small>";
			}
			$credits_granted_r = intval($row_coupon_redemptions['credits_granted']);
			$credits_used_r = intval($row_coupon_redemptions['credits_used']);
			$credits_remaining_r = max(0, $credits_granted_r - $credits_used_r);
			if ($credits_used_r >= $credits_granted_r) {
				$used_display = "<span class=\"text-danger\">".$credits_used_r." / ".$credits_granted_r."</span>";
			} elseif ($credits_used_r > 0) {
				$used_display = "<span class=\"text-warning\">".$credits_used_r." / ".$credits_granted_r."</span>";
			} else {
				$used_display = "<span class=\"text-success\">0 / ".$credits_granted_r."</span>";
			}
		?>
		<tr>
			<td><?php echo htmlspecialchars($row_coupon_redemptions['code']); ?></td>
			<td><?php echo $user_display; ?></td>
			<td><?php echo $credits_granted_r; ?></td>
			<td><?php echo $used_display; ?></td>
			<td><?php echo getTimeZoneDateTime($_SESSION['prefsTimeZone'], strtotime($row_coupon_redemptions['redeemed_at']), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time-no-gmt"); ?></td>
		</tr>
		<?php } while ($row_coupon_redemptions = mysqli_fetch_assoc($coupon_redemptions)); ?>
	</tbody>
</table>
<?php } else { ?>
<p>No voucher redemptions have been recorded yet.</p>
<?php } ?>
