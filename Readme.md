1. Define a competition from WP Admin - Submissions - Competitions
2. Each defined competition gets an ID, the competition taxonomy ID from WordPress.
3. Once you defined the competition and enabled the submissions then people are able to start submitting solutions.
4. When users submit solutions they are automatically uploaded to this path:
   1. Regular WP site: ABSOLUTE_PATH_TO_WP_INSTALL/wp-content/uploads/iarai-submissions/{competition_ID}/{linux-time}-{user_ID}-{randon_number}.{file_extension} 
   2. Multisite WP: ABSOLUTE_PATH_TO_WP_INSTALL/wp-content/uploads/sites/{site_ID}/iarai-submissions/{competition_ID}/{linux-time}-{user_ID}-{randon_number}.{file_extension}
5. Cron and getting the score
   1. Depending on the cron frequency defined when you created the competition the system will check for score files to be processed.
   2. The system expects a file in the exact location with the uploaded submission with the exact file name but replace the extension with the .score extension
   3. The file should contain only the score number
   4. You can also add a log that appear when viewing the submissions as an user. The log file needs to have the same path and filename as the submission but replace the extension with .log file extension
