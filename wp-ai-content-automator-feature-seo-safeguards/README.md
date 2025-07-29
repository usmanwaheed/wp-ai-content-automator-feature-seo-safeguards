# Zip the project folder for upload via WP Dashboard

# linux

zip -r wp-ai-content-automator.zip wp-ai-content-automator/

# Windows (one folder above zip folder)
Compress-Archive -Path .\wp-ai-content-automator\* `
                 -DestinationPath wp-ai-content-automator.zip `
                 -Force


# PUSH TO HOSTINGER
scp -r -P 65002 ./* u230152334@89.117.9.205:/home/u230152334/public_html/wp-content/plugins/wp-ai-content-automator/