# Database Connection Troubleshooting

Private Gather treats an empty Database port as **Auto**.

For a local MySQL server:

- Host `localhost`, port blank: test native localhost/socket transport first, then forced TCP at `127.0.0.1:3306`.
- Host `localhost`, explicit port: force TCP through `127.0.0.1:<port>`.
- Host `127.0.0.1`, port blank: use TCP with the driver's default MySQL port.
- Remote/database-cluster host: preserve the supplied hostname; an explicit port is used only when entered.

PDO/MySQL can use a Unix socket whenever the host is `localhost`, even if a port appears in the DSN. That is why simply changing the port field while leaving the host as `localhost` is not a reliable way to test TCP.

If Private Gather reports MySQL error 1045 after both local socket and forced TCP were tested, verify the exact database username and password and confirm the database user is assigned privileges to the selected database. Hosting panels may prefix both database names and database usernames.
