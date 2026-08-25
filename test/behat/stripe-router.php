<?php
declare(strict_types=1);

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$method = $_SERVER["REQUEST_METHOD"];
parse_str((string)file_get_contents("php://input"), $input);

header("Content-Type: application/json");

if($method === "POST" && $path === "/hexform-test-webhook") {
	http_response_code(202);
	respond(["received" => true]);
}

if($method === "GET" && $path === "/v1/prices") {
	$lookupKey = $_GET["lookup_keys"][0] ?? "";
	respond([
		"object" => "list",
		"data" => [[
			"id" => priceId($lookupKey),
			"object" => "price",
			"active" => true,
			"currency" => "gbp",
			"lookup_key" => $lookupKey,
			"product" => "prod_V3OQQx0aSQ9LRI",
			"unit_amount" => $lookupKey === "hexform_enterprise_gbp_monthly" ? 2500 : 500,
		]],
		"has_more" => false,
		"url" => "/v1/prices",
	]);
}

if(str_starts_with((string)$path, "/v1/subscriptions/sub_behat")) {
	http_response_code(500);
	respond(["error" => ["type" => "api_error", "message" => "Deliberate Behat failure"]]);
}

if($method === "POST" && $path === "/v1/subscriptions/sub_success") {
	$price = $input["items"][0]["price"] ?? "price_developer";
	$plan = $price === "price_enterprise" ? "enterprise" : "developer";
	$cancelAtPeriodEnd = ($input["cancel_at_period_end"] ?? "false") === "true";
	respond(subscription($plan, $cancelAtPeriodEnd));
}

if($method === "GET" && $path === "/v1/subscriptions/sub_success") {
	respond(subscription("developer", false));
}

if($method === "GET" && $path === "/v1/invoices") {
	respond([
		"object" => "list",
		"data" => [
			invoice(2000, 2000, "2026-08-12 14:00:00 UTC"),
			invoice(500, 500, "2026-08-12 13:20:58 UTC"),
		],
		"has_more" => false,
		"url" => "/v1/invoices",
	]);
}

if($method === "POST" && $path === "/v1/invoices/create_preview") {
	respond(invoice(2500, 0));
}

if($method === "POST" && $path === "/v1/subscription_schedules") {
	respond(schedule());
}

if($method === "POST" && $path === "/v1/subscription_schedules/sched_success") {
	respond(schedule());
}

http_response_code(404);
respond(["error" => ["type" => "invalid_request_error", "message" => "Unknown test request"]]);

function priceId(string $lookupKey):string {
	return $lookupKey === "hexform_enterprise_gbp_monthly"
		? "price_enterprise"
		: "price_developer";
}

/** @return array<string, mixed> */
function subscription(string $plan, bool $cancelAtPeriodEnd):array {
	$periodEnd = strtotime("2026-09-12 13:20:58 UTC");
	return [
		"id" => "sub_success",
		"object" => "subscription",
		"customer" => "cus_behat_success",
		"status" => "active",
		"cancel_at_period_end" => $cancelAtPeriodEnd,
		"items" => ["object" => "list", "data" => [[
			"id" => "si_success",
			"object" => "subscription_item",
			"current_period_end" => $periodEnd,
			"price" => [
				"id" => "price_$plan",
				"object" => "price",
				"currency" => "gbp",
				"lookup_key" => "hexform_{$plan}_gbp_monthly",
			],
		]]],
	];
}

/** @return array<string, mixed> */
function invoice(
	int $amountDue,
	int $amountPaid,
	string $paidAt = "2026-08-12 13:20:58 UTC",
):array {
	return [
		"id" => "in_success_$amountDue",
		"object" => "invoice",
		"currency" => "gbp",
		"amount_due" => $amountDue,
		"amount_paid" => $amountPaid,
		"status" => "paid",
		"status_transitions" => ["paid_at" => strtotime($paidAt)],
	];
}

/** @return array<string, mixed> */
function schedule():array {
	return [
		"id" => "sched_success",
		"object" => "subscription_schedule",
		"status" => "active",
		"current_phase" => [
			"start_date" => strtotime("2026-08-12 13:20:58 UTC"),
			"end_date" => strtotime("2026-09-12 13:20:58 UTC"),
		],
	];
}

/** @param array<string, mixed> $data */
function respond(array $data):never {
	echo json_encode($data, JSON_THROW_ON_ERROR);
	exit;
}
