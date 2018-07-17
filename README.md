# Shopify-Private-Importer #

##Adding a new Shopify Importer client.##

1. Log-in to tech server as ec2-user.
2. Navigate to /var/www/html/tech/shopify/clients
3. Add a new directory for client in the /clients folder.
4. Create 2 new files or copy files from an existing client folder.  The files should be importer.php and shop-data.JSON.  Importer.php will generally be identical across all clients unless it has been decided to override the MasterImporter class.  Shop-data.JSON will contain a standard JSON object with all of the client's pertinent Shopify and Brafton credentials/options.  Please refer to the example folder found here in the master branch.

`{<br />
	"store_name" : "Shopify store name here",<br />
	"shop_private" : "Shopify Private Application key here",<br />
	"shop_pw":"Shopify Private Application key password here",<br />
	"blog_id" : "Shopify Blog id here",<br />
	"brafton_api" : "Standard Brafton API Key",<br />
        "video" : true/false, //will the client subscribe to Brafton video blogs<br />
	"brafton_private":"Brafton private key",<br />
	"brafton_public":"Brafton public key"<br />
}<br />`

5. Set a cron/scheduler job on the tech server.
	Run command crontab -e
	Press esc "i" to edit.
	Tab to bottom of page and a new line.
	Add 5 time parameters:
		
	    minute of the hour
	    Hour of the day
	    Day of the Month
	    Month
	    Day of the Week
	add /usr/bin/wget<br />
	add full http path to importer file in new client folder.<br />
	17 2 * * 1-5 /usr/bin/wget http://tech.brafton.com/shopify/clients/{client-name}/importer.php

	
