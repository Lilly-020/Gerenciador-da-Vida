# Módulo Social

O Social usa a sessão Laravel e o menu lateral existentes. A interface é montada em Vue 3 na página Blade do módulo, com carregamento pelo Vite. O feed mostra publicações autorizadas da comunidade, da mais recente para a mais antiga. Somente o autor e os amigos aceitos podem ver publicações novas ou anteriormente públicas. Publicações antigas marcadas como **Somente eu** continuam reservadas ao autor.

O feed carrega lotes de até 15 publicações e mantém até 15 cartões renderizados durante a rolagem. Com a aba visível, consulta novas publicações a cada cinco segundos usando o parâmetro `after` do endpoint `/social/feed`.

O editor de publicações aceita formatação e emojis. A migration `add_content_html_to_social_posts_table` adiciona uma coluna opcional para armazenar a formatação sanitizada pelo servidor. Publicações anteriores continuam com o texto original.

## Banco de dados

A migration `create_social_tables` cria `social_profiles`, `social_friendships`, `social_blocks`, `social_posts`, `social_post_media`, `social_post_likes` e `social_comments`. Os vínculos usam IDs da tabela `users`. A migration `merge_social_friendships_into_amizades` incorpora as amizades do Social à tabela `amizades`, compartilhada com **Perfil e amigos**. A tabela antiga fica disponível para histórico. A amizade usa um par canônico de IDs com índice único e as curtidas têm chave primária composta. Posts e comentários usam exclusão lógica. Nenhum seeder do Social é executado pelo seeder principal.

Para subir a versão em ambiente local, execute `php artisan migrate` e `npm run build`. Para produção, inclua a migration no fluxo normal de deploy. A aplicação usa a tabela de notificações existente e o disco privado `local`, que precisa estar disponível no ambiente de execução. Em uma instalação com várias instâncias da aplicação, monte o mesmo armazenamento privado em todas as instâncias.

## Comunidades

O cadastro existente não tem uma entidade de empresa ou tenant. Em uma instalação dedicada a uma única comunidade, deixe `SOCIAL_COMMUNITIES` ausente. Para uma instalação compartilhada entre empresas, configure a variável antes de disponibilizar o módulo. Exemplo de mapeamento de IDs internos de usuários para comunidades:

```dotenv
SOCIAL_COMMUNITIES='{"12":"empresa-a","18":"empresa-a","31":"empresa-b"}'
```

Usuários com o mesmo identificador podem se encontrar pelo nome. Com a variável configurada, contas ausentes do mapa ficam isoladas e uma configuração inválida também deixa as contas isoladas. Um mapa vazio (`{}`) isola todas as contas. Atualize o mapa quando houver admissões, desligamentos ou transferências. Se o ambiente usa cache de configuração, recrie o cache depois da mudança. O navegador não recebe o mapa. Alterações na comunidade restringem também o acesso por URL aos perfis, publicações e imagens existentes.

## Rotas e permissões

| Rotas | Permissões |
| --- | --- |
| `GET /social`, `/social/feed`, `/social/friends`, `/social/requests`, `/social/people` | Usuário autenticado. Feed e buscas têm paginação por cursor e respeitam os limites de comunidade e bloqueio. |
| `GET /social/profile/{person}`, `/social/profile/{person}/data`, `/social/profile/{person}/image/{image}` | Perfis e fotos são visíveis dentro da comunidade, considerando bloqueios nos dois sentidos. |
| `POST /perfil/social` | Pela aba **Meus dados** em **Perfil e amigos**, atualiza apenas a biografia, o avatar e a capa social do usuário autenticado. |
| `POST /social/posts`, `PATCH/DELETE /social/posts/{post}` | O autor pode criar, editar o texto e excluir a publicação. |
| `GET /social/media/{media}` | Requer acesso à publicação associada. As imagens são servidas sem cache público. |
| `POST /social/posts/{post}/like`, `GET/POST /social/posts/{post}/comments` | Requer acesso à publicação. Respostas só podem referenciar comentários visíveis da mesma publicação. |
| `PATCH/DELETE /social/comments/{comment}` | Apenas o autor do comentário, enquanto tiver acesso à publicação. |
| `POST /social/friendships`, `PATCH/DELETE /social/friendships/{friendship}` | O destinatário aceita ou recusa. Quem enviou pode cancelar. Qualquer amigo pode desfazer a amizade. |
| `POST/DELETE /social/blocks/{person}` | Cada pessoa gerencia os próprios bloqueios. Bloquear remove a amizade ou solicitação existente. |
| `GET /social/notifications`, `POST /social/notifications/{notification}` | Apenas o destinatário. A abertura valida novamente o acesso à publicação, se houver. |

Escritas passam pela proteção CSRF do Laravel. Criação de posts, comentários, solicitações, curtidas, bloqueios e edição de perfil têm limites de frequência. As publicações aceitam até seis anexos JPEG, PNG ou WebP, com até 5 MB por imagem. O perfil aceita os mesmos formatos e limites. Os arquivos de posts removidos ficam inacessíveis pela rota autorizada enquanto o registro estiver excluído.

## Verificação

```sh
npm run build
vendor/bin/phpunit tests/Feature/SocialTest.php
```
