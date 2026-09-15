<template>
  <ArcadeGame game-slug="snake" game-id="snake" title="🐍 뱀 게임" :points-per-score="2" :lines="[
    '방향키(또는 화면 스와이프)로 뱀의 이동 방향을 조종해요.',
    '먹이를 먹을 때마다 뱀 길이가 길어지고 점수가 올라가요.',
    '벽이나 자기 몸에 부딪히면 게임 오버! 최대한 오래, 길게 살아남아보세요.',
  ]" />
</template>
<script setup>
import ArcadeGame from './ArcadeGame.vue'
</script>
