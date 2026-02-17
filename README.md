# backendsnretratos

## Integração com o Frontend

1. Certifique-se de que o arquivo `connect/cors.php` contém o domínio do frontend em `$origensPermitidas`.
2. Para desenvolvimento local, adicione por exemplo:
	```php
	'http://localhost:3000',
	```
3. O backend espera variáveis de ambiente como `DB_URL` para conexão com o banco de dados.
4. Para rodar localmente, use um servidor PHP apontando para a pasta `backend-sn`.
5. O backend responde a requisições do frontend em `/components/*.php`.
6. Certifique-se de que o CORS está habilitado corretamente para o domínio do frontend.
