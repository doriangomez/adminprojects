# Pseudotests: flujo de creación de talento

## Caso 1: usuario existente sin talento asociado
1. **Arrange**
   - Insertar `users.email = "existente@empresa.com"` sin registro en `talents.user_id`.
2. **Act**
   - Ejecutar `TalentService::createTalent` con `email = existente@empresa.com`.
3. **Assert**
   - Se crea un nuevo talento.
   - `talents.user_id` corresponde al `users.id` existente.
   - `status = existing_user_reused`.

## Caso 2: usuario existente con talento asociado
1. **Arrange**
   - Insertar `users.email = "duplicado@empresa.com"` y un talento con ese `user_id`.
2. **Act**
   - Ejecutar `TalentService::createTalent` con `email = duplicado@empresa.com`.
3. **Assert**
   - Lanza `InvalidArgumentException` con mensaje: `El usuario ya está registrado como talento`.
   - No se crea un nuevo registro en `talents`.

## Caso 3: usuario nuevo con email
1. **Arrange**
   - Garantizar que `users.email = "nuevo@empresa.com"` no exista.
2. **Act**
   - Ejecutar `TalentService::createTalent` con `email = nuevo@empresa.com`.
3. **Assert**
   - Se crea un nuevo registro en `users`.
   - Se crea un nuevo talento con `talents.user_id` igual al nuevo `users.id`.
   - `status = new_user_created`.

## Caso 4: talento operativo / sin email
1. **Arrange**
   - Payload sin campo email o con email vacío.
2. **Act**
   - Ejecutar `TalentService::createTalent`.
3. **Assert**
   - Se crea talento con `talents.user_id = NULL`.
   - No se crea registro en `users`.
   - `status = talent_without_access`.
