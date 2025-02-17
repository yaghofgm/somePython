import numpy as np

# Given values for t and S(t)
t_values = np.array([47,74,273])
S_values = np.array([11.5,12,12])

#Coefficient matrix A
A=np.zeros((3,3))
for i, t in enumerate(t_values):
    A[i,0] = 1
    A[i,1] = np.cos(2 * np.pi * t / 365)
    A[i, 2] = np.sin(2* np.pi * t / 365)

# Solve the system Ax = B
coefficients = np.linalg.solve(A, S_values)

a, b, c = coefficients

print(f"a = {a:.4f}, b = {b:.4f}, c = {c:.4f}")

def S(t):
    return a + b * np.cos(2 * np.pi * t / 365) + c * np.sin(2 * np.pi * t / 365)

t_days = np.arange(0,366)
day_lengths = S(t_days)
max_lengths = S(t_days)
max_length = np.max(day_lengths)
day_of_max_length = t_days[np.argmax(day_lengths)]

print(f"The longest day length is {max_length:.4f} hours on day {day_of_max_length} of the year")