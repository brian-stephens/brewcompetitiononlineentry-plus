<?php
/**
 * Module:      coupons.pub.php
 * Description: Display coupon credit balance and coupon redemption form.
 */

$coupon_credits_available = coupon_available_credits($_SESSION['user_id']);

// Get total credits applied to entries (for per-row status calculation)
$query_credits_used = sprintf(
	"SELECT COUNT(*) AS credits_used FROM %s WHERE user_id='%s'",
	$prefix."coupon_entry_payments",
	intval($_SESSION['user_id'])
);
$result_credits_used = mysqli_query($connection, $query_credits_used);
$total_credits_used = 0;
if ($result_credits_used) {
	$row_credits_used = mysqli_fetch_assoc($result_credits_used);
	$total_credits_used = intval($row_credits_used['credits_used']);
}

// Fetch oldest first so FIFO accounting works correctly
$query_coupon_redemptions = sprintf(
	"SELECT r.credits_granted, r.redeemed_at, c.code
	 FROM %s r
	 LEFT JOIN %s c ON r.coupon_code_id = c.id
	 WHERE r.user_id='%s'
	 ORDER BY r.redeemed_at ASC",
	$prefix."coupon_redemptions",
	$prefix."coupon_codes",
	intval($_SESSION['user_id'])
);
$coupon_redemptions = mysqli_query($connection,$query_coupon_redemptions) or die (mysqli_error($connection));
$totalRows_coupon_redemptions = mysqli_num_rows($coupon_redemptions);

// Build rows with per-voucher status via FIFO
$coupon_redemption_rows = [];
$remaining_to_account = $total_credits_used;
while ($r = mysqli_fetch_assoc($coupon_redemptions)) {
	$granted = intval($r['credits_granted']);
	if ($remaining_to_account >= $granted) {
		$r['status'] = 'used';
		$remaining_to_account -= $granted;
	} elseif ($remaining_to_account > 0) {
		$r['status'] = 'partial';
		$r['credits_remaining'] = $granted - $remaining_to_account;
		$remaining_to_account = 0;
	} else {
		$r['status'] = 'available';
	}
	$coupon_redemption_rows[] = $r;
}
$coupon_redemption_rows = array_reverse($coupon_redemption_rows);

?>
<a class="anchor-offset" name="coupons"></a>
<h2><?php echo $label_coupons; ?></h2>

<div class="card bg-light-subtle border-secondary-subtle mb-3">
	<div class="card-body">
		<div class="row g-2">
			<div class="col-12 col-lg-4">
				<div class="card h-100 border-dark-subtle">
					<div class="card-body text-center">
						<div class="text-muted"><?php echo $label_coupon_credits; ?></div>
						<div class="display-6 fw-semibold"><?php echo intval($coupon_credits_available); ?></div>
					</div>
				</div>
			</div>
			<div class="col-12 col-lg-8">
				<form method="post" class="row g-2" action="<?php echo $base_url; ?>includes/process.inc.php?section=list&amp;action=redeem_coupon&amp;id=<?php echo intval($_SESSION['user_id']); ?>">
					<input type="hidden" name="user_session_token" value ="<?php if (isset($_SESSION['user_session_token'])) echo htmlspecialchars($_SESSION['user_session_token'], ENT_QUOTES, 'UTF-8'); ?>">
					<div class="col-12 col-md-8">
						<label class="form-label" for="couponCode"><?php echo $label_coupon_code; ?></label>
						<input id="couponCode" name="couponCode" type="text" maxlength="64" class="form-control" placeholder="<?php echo $label_coupon_code; ?>" required>
					</div>
					<div class="col-12 col-md-4 d-grid">
						<label class="form-label d-none d-md-block">&nbsp;</label>
						<button type="submit" class="btn btn-primary"><i class="fa fa-ticket me-2"></i><?php echo $label_redeem_coupon; ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<?php if ($totalRows_coupon_redemptions > 0) { ?>
<div class="table-responsive mb-3">
	<table class="table table-bordered table-striped border-dark-subtle">
		<thead class="table-dark">
			<tr>
				<th><?php echo $label_coupon_code; ?></th>
				<th><?php echo $label_coupon_credits; ?></th>
				<th>Status</th>
				<th><?php echo $label_date; ?></th>
			</tr>
		</thead>
		<tbody class="table-group-divider">
			<?php foreach ($coupon_redemption_rows as $row_coupon_redemptions) {
				if ($row_coupon_redemptions['status'] === 'used') {
					$status_badge = '<span class="badge bg-secondary">Used</span>';
				} elseif ($row_coupon_redemptions['status'] === 'partial') {
					$status_badge = '<span class="badge bg-warning text-dark">'.intval($row_coupon_redemptions['credits_remaining']).' of '.intval($row_coupon_redemptions['credits_granted']).' remaining</span>';
				} else {
					$status_badge = '<span class="badge bg-success">Available</span>';
				}
			?>
			<tr>
				<td><?php echo htmlspecialchars($row_coupon_redemptions['code']); ?></td>
				<td><?php echo intval($row_coupon_redemptions['credits_granted']); ?></td>
				<td><?php echo $status_badge; ?></td>
				<td><?php echo getTimeZoneDateTime($_SESSION['prefsTimeZone'], strtotime($row_coupon_redemptions['redeemed_at']), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time-no-gmt"); ?></td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
<?php } ?>
