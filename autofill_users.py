import requests
import os
import json
loginURL = "http://pickup.prateekchandan.me/add_user"
HTTPSession = requests.session()
start_locations=['Larsen+Tourbo+Powai','IIT+Bombay+Hostel+9','Hiranandani+Hospital','Hiranandani','Kanjur+Marg+Station',
'Chandivali','Raheja+Vihar','Supreme+Powai','Galleria+Hiranandani','Powai+Plaza']

for i in range(100):
	postData = {'key':'9f83c32cf3c9d529e' ,'fbid':i ,'name':'person'+str(i) , 'email':'person'+str(i)+'@gmail.com' ,
	'device_id':i , 'gcm_id':i, 'mac_addr':i , 'gender':'male'}
	afterLoginPage = HTTPSession.post(loginURL, data = postData )
	print afterLoginPage.content
		

#afterLoginPage = HTTPSession.post(loginURL, data = postData )

#print afterLoginPage.content
