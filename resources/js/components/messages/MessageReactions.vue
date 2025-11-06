<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    currentUserId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['reaction-added', 'reaction-removed']);

const reactions = ref(props.message.reactions || []);
const showReactionPicker = ref(false);

const availableReactions = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

const hasReaction = (reaction) => {
    return reactions.value.some((r) => r.reaction === reaction);
};

const getUserReaction = (reaction) => {
    return reactions.value.find((r) => r.reaction === reaction)?.users?.find((u) => u.id === props.currentUserId);
};

const addReaction = async (emoji) => {
    try {
        const response = await axios.post(`/messages/${props.message.id}/reactions`, {
            reaction: emoji,
        });

        // Update local reactions
        const reactionIndex = reactions.value.findIndex((r) => r.reaction === emoji);
        if (reactionIndex >= 0) {
            // Update existing reaction
            reactions.value[reactionIndex].count++;
            if (!reactions.value[reactionIndex].users.find((u) => u.id === props.currentUserId)) {
                reactions.value[reactionIndex].users.push({
                    id: props.currentUserId,
                    name: response.data.reaction.user.name,
                });
            }
        } else {
            // Add new reaction
            reactions.value.push({
                reaction: emoji,
                count: 1,
                users: [
                    {
                        id: props.currentUserId,
                        name: response.data.reaction.user.name,
                    },
                ],
            });
        }

        emit('reaction-added', response.data.reaction);
        showReactionPicker.value = false;
    } catch (error) {
        console.error('Error adding reaction:', error);
    }
};

const removeReaction = async (emoji) => {
    try {
        const reaction = reactions.value.find((r) => r.reaction === emoji);
        if (!reaction) return;

        const userReaction = reaction.users.find((u) => u.id === props.currentUserId);
        if (!userReaction) return;

        // Find the reaction ID from the backend
        const reactionsResponse = await axios.get(`/messages/${props.message.id}/reactions`);
        const allReactions = reactionsResponse.data.reactions;
        const reactionData = allReactions.find((r) => r.reaction === emoji);
        if (!reactionData) return;

        const userReactionData = reactionData.users.find((u) => u.id === props.currentUserId);
        if (!userReactionData) return;

        // We need the reaction ID, but we don't have it directly
        // So we'll need to get it from the backend
        const reactionId = userReactionData.id || reactionData.id;

        await axios.delete(`/messages/${props.message.id}/reactions/${reactionId}`);

        // Update local reactions
        reaction.count--;
        reaction.users = reaction.users.filter((u) => u.id !== props.currentUserId);

        if (reaction.count === 0) {
            reactions.value = reactions.value.filter((r) => r.reaction !== emoji);
        }

        emit('reaction-removed', { reaction: emoji });
    } catch (error) {
        console.error('Error removing reaction:', error);
    }
};

const toggleReaction = (emoji) => {
    if (getUserReaction(emoji)) {
        removeReaction(emoji);
    } else {
        addReaction(emoji);
    }
};
</script>

<template>
    <div class="message-reactions flex flex-wrap gap-1 mt-1">
        <button
            v-for="reaction in reactions"
            :key="reaction.reaction"
            @click="toggleReaction(reaction.reaction)"
            :class="[
                'reaction-button flex items-center gap-1 px-2 py-1 rounded-full text-xs',
                getUserReaction(reaction.reaction) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700',
            ]"
        >
            <span>{{ reaction.reaction }}</span>
            <span v-if="reaction.count > 1">{{ reaction.count }}</span>
        </button>

        <button
            @click="showReactionPicker = !showReactionPicker"
            class="reaction-button-add px-2 py-1 rounded-full text-xs bg-gray-200 text-gray-700 hover:bg-gray-300"
        >
            +
        </button>

        <div v-if="showReactionPicker" class="reaction-picker absolute bg-white border rounded-lg shadow-lg p-2 flex gap-2 z-10">
            <button
                v-for="emoji in availableReactions"
                :key="emoji"
                @click="addReaction(emoji)"
                class="reaction-emoji text-xl hover:scale-125 transition-transform"
            >
                {{ emoji }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.message-reactions {
    position: relative;
}

.reaction-picker {
    position: absolute;
    bottom: 100%;
    left: 0;
    margin-bottom: 4px;
}
</style>

