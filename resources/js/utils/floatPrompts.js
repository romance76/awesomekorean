// 화면 한쪽에 떠 있는 작은 안내 버튼들이 서로 겹치지 않게 조율하는 공용 상태.
// 출석체크 버튼이 떠 있는 동안에는 NEW 광고 안내가 나오지 않는다.
import { ref } from 'vue'

export const checkinVisible = ref(false)
