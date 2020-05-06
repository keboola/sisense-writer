# SiSense writer

[![Build Status](https://travis-ci.com/keboola/sisense-writer.svg?branch=master)](https://travis-ci.com/keboola/sisense-writer)

> Exports data to [SiSense database](https://www.sisense.com/)

# Usage

### Configuration
The configuration file contains following properties:
- `db`
    - `host` - string (required): host or ip address to your sisense workspace
    - `port` - string (optional): port where is running sisence (default 30845)
    - `username` - string (required): username for sisense
    - `#password` - string (required): password for sisense
- `dbName` - string (required): name of datamodel in sisense
- `tableId` - string (required): name of target table 
- `items` - array (required): list columns
    - `id` - string (required): unique identifier of table column
    - `name` - string (required): name table column
    - `type` - string (required): type of column
    - `size` - string (required): length column
- `relationships` - array (optional): relationship, target table and column must exists
    - `column` - string (required): source column of the relation
    - `target` - array (required)
        - `table` - string (required): target table of the relation
        - `column` - string (required): target column of the relation

Example of config.json
```json
{
    "parameters": {
        "db": {
            "host": "xxx",
            "port": "xxx",
            "username": "xxx",
            "#password": "xxx",
        },
        "tableId": "testTable",
        "dbName": "KeboolaDatamodelName",
        "items": [
            {
                "id": "id",
                "name": "id",
                "type": "int",
                "size": "10"
            },
            {
                "id": "first_name",
                "name": "first name",
                "type": "varchar",
                "size": "255"
            },
            {
                "id": "last_name",
                "name": "last name",
                "type": "varchar",
                "size": "255"
            },
            {
                "id": "country",
                "name": "country",
                "type": "varchar",
                "size": "255"
            }
        ]
    }
}
```

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/sisense-writer
cd sisense-writer
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Create `.env` file with following variables:
```
SISENSE_HOST=
SISENSE_PORT=
SISENSE_USERNAME=
SISENSE_PASSWORD=
SISENSE_DATAMODEL=
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
