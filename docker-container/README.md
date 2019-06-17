# Based On:

[https://github.com/alexcheng1982/docker-magento](https://github.com/alexcheng1982/docker-magento)

With M1 being an older service there is no official support from the platform. Through my research this was the best overall solution giving a full breakdown of the process.

# Dependencies

## ngrok

[https://ngrok.com/](https://ngrok.com/)
[https://ngrok.com/pricing](https://ngrok.com/pricing)

In order for your docker container to communicate with the webhook and shipping & tax url set on [merchant-sandbox.bolt.com](http://merchant-sandbox.bolt.com/), one must set up an ngrok account to expose your store's docker ports to a public IP address. In order to do this one must have at least the Basic plan with ngrok to reserve a domain for themselves. 

Once the initial configuration scripts are ran in your local environment and a public domain is reserved, run the following command 

    ./ngrok http <STORE_PORT> -subdomain=<NGROK_DOMAIN>

***Make sure not to include [ngrok.io](http://ngrok.io) in your domain***. For example if your store is [test-store.ngrok.io](http://test-store.ngrok.io), **just enter test-store** for the domain. 

## Launch Script Dependencies

### Python:

Make sure you have a stable version of python in your environment. These scripts were ran with 2.7.15.

Ensure you have the yaml library is installed for your python version. 

    pip install pyyaml

# **How to run**

- Set the initial variables of config.sh to match your environment and versioning
```
    #VARIABLES
    m1Version="1.9.1.0"
    boltBranch="develop"
    boltRepo="/Users/ewayda/Documents/GitHub/bolt-magento1"
    localRepo=false
    boltApiKey=""
    boltSigningSecret=""
    boltPublishableKey=""
    ngrokUrl="ethan-m1.ngrok.io"
    installSampleData=true
```

- Run **launch-m1.sh** to fully deploy a docker container containing M1 store with Bolt
- Visit store at your ngrok url
    - Admin credentials can be found in the env file once dowloaded username: **admin** password: **magentorocks1**

## **Magento versions available to run**

Version | Tag name | PHP Version
--------|--------- |---------
1.9.3.8 | latest   | 5.6.33
1.9.1.1 | 1.9.1.0  | 5.6.33
1.8.1.0 | 1.8.1.0  | 5.5.30
1.7.0.2 | 1.7.0.2  | 5.5.30
1.6.2.0 | 1.6.2.0  | 5.5.30

**Note** For this current iteration php is fixed

# File breakdown:

**change-hostname.py**  - Changes the hostname for the store so that it can work with ngrok

**setup-magento.php** - Setups the magento store so that bolt is active with the provided bolt keys
