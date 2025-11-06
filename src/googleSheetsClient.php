<?php

use Google\Service\Sheets\ValueRange;

class googleSheetsClient
{
    private $path = "src/sheets_api.json";
    private $spreadsheetId;
    private $client;
    private $service;

    /**
     * Constructs a new GoogleSheets instance.
     *
     * @param string $spreadsheetId The ID of the Google Sheets spreadsheet
     * to access.
     */
    public function __construct($spreadsheetId)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->initializeClient($this->spreadsheetId);
    }

    /**
     * Initializes the Google API client.
     *
     * This method sets up the client with the credentials in the
     * $this->path file, and adds the Google Sheets scope to the client.
     *
     * It then creates a new Google_Service_Sheets instance from the client,
     * which is stored in the $this->service property.
     */
    private function initializeClient()
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig($this->path);
        $this->client->addScope('https://www.googleapis.com/auth/spreadsheets');
        $this->service = new Google_Service_Sheets($this->client);
    }

    /**
     * Get the sheets in a spreadsheet.
     *
     * @return Google_Service_Sheets_Sheet[] The sheets in the spreadsheet.
     */
    public function getSheets()
    {
        $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
        return $spreadsheet->getSheets();
    }

    /**
     * Get values from a spreadsheet.
     *
     * @param string $range The [A1 notation](https://developers.google.com/workspace/sheets/api/guides/concepts#cell)
     * of a range to search for a logical table of data.
     * @return array[] The values in the specified range.
     */
    public function getValues($range)
    {
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
        return $response->getValues();
    }

    /**
     * Updates values in a spreadsheet. The input range is used to search for
     * existing data and find a "table" within that range. Values will be appended
     * to the next row of the table, starting with the first column of the table.
     *
     * @param string $range The [A1 notation](https://developers.google.com/workspace/sheets/api/guides/concepts#cell)
     * of a range to search for a logical table of data.
     * @param array[] $values The values to append to the table.
     *
     * @return Google_Service_Sheets_UpdateValuesResponse The update response.
     */
    public function updateValues($range, $values)
    {
        $body = new ValueRange(['values' => $values]);
        return $this->service->spreadsheets_values->update(
            $this->spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'RAW']
        );
    }
}
