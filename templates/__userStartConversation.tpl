{if MODULE_CONVERSATION && $__wcf->user->userID && $__wcf->session->getPermission('user.conversation.canUseConversation') && $user->userID != $__wcf->user->userID}<li><a class="button jsTooltip" href="{link controller='ConversationAdd'}userID={@$user->userID}{/link}" title="{lang}wcf.conversation.button.add{/lang}"><img src="{icon}comment{/icon}" alt="" class="icon24" /></a></li>{/if}