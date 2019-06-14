import yaml
import sys
"""
Edits docker-compose.yml to use containers based off of the specified magento version
"""
data_map = {}

with open('docker-compose.yml') as yml_file:  
    data_map = yaml.safe_load(yml_file)
    m1_image = data_map["services"]["web"]["image"]
    data_map["services"]["web"]["image"] = m1_image.split(":")[0] + ":" + sys.argv[1]


with open('docker-compose.yml', "w") as output_file:
    yaml.dump(data_map, output_file, default_flow_style=False)