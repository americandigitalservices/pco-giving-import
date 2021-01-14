# pushpay-to-pco-giving
PHP code to migrate exported PushPay giving records into to Planning Center Giving (PCO Giving)

# Languages
Requires PHP 7.0+ and will soon require cURL.
Works on Linux, Windows, and Mac via CLI or using a web server.

# Authentication 
The script uses a personal access token, which can be found from your Planning Center Online account (api.planningcenteronline.com). Paste these values into the fields at the top of the script

# Preparation
Create a Payment Source in PCO Giving to receive the transactions - then update the value for G_PAYMENT_SOURCE_ID in the script.
Create a fund in PCO Giving to receive the transactions - then update the array values for $funds_index in the script.
Add one field in a custom tab in PCO for individial ID and update the value for P_TAB_FIELD_ID in the script.
Import the people csv into PCO People directly (working on a people import feature) and verify that your tab field has been populated.
Locate the files in one folder that php can run from.

# Matching Donations to People 
We automatically create a relational array from PCO people that includes the individial ID you imported (G_PERSON_ID in the giving csv) and match the users automatically.

# Running the import 
We suggest breaking this up into reasonable batches - we pulled in about 5000 in a batch that takes about 15 to 20 minutes to run.

php giving.php > this_month.txt

After the script finishes running, I go and manually enter any donations with errors it encountered, verify the totals across both databases, and then commit the batch 






