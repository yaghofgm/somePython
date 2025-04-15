#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
Script para calcular opciones de préstamo para el sitio web,
utilizando la misma lógica que el archivo calculate_loanOptions.py
"""

import sys
import json
import math
import numpy as np
from datetime import datetime

def salary_vector_prediction(GPA, sex, university, course):
    """
    Predicts a salary vector over 10 years (120 months) with stepwise growth.
    The salary increases once per year and stays fixed for 12 months.
    
    Parameters:
    GPA (float): The student's GPA.
    sex (str): The student's sex ('male' or 'female').
    university (str): The university name.
    course (str): The course name.
    
    Returns:
    list: A 120-element list representing monthly salaries over 10 years.
    """
    
    def base_salary(GPA, sex, university, course):
        # Default salary data by university and course
        salary_data = {
            "Tecnologico de Monterrey Estado de Mexico": {
                "Ingenieria Mecanica": 10000,
                "Ingeniería Quimica": 12500,
                "Ingeniería Computacion y Tecnologias de la informacion": 15000,
            },
            "Anahuac": {
                "Ingenieria Industrial": 10000,
                "Ingenieria Mecanica": 10000,
            },
            "University of Michigan Ann Arbor": {
                "Electrical & Computer Engineering": 77300,
                "Mechanical Engineering": 89668,
            }
        }

        # Default GPA data by university and course
        gpa_data = {
            "Tecnologico de Monterrey Estado de Mexico": {
                "Ingenieria Mecanica": 3.5,
                "Ingeniería Quimica": 3.6,
                "Ingeniería Computacion y Tecnologias de la informacion": 3.7,
            },
            "Anahuac": {
                "Ingenieria Industrial": 3.5,
                "Ingenieria Mecanica": 3.4,
            },
            "University of Michigan Ann Arbor": {
                "Electrical & Computer Engineering": 3.7,
                "Mechanical Engineering": 3.6,
            }
        }

        # Get the base salary and average GPA for the university/course, or use defaults
        raw_salary = salary_data.get(university, {}).get(course, 50000)  # default salary is 50000 USD
        gpa_avg = gpa_data.get(university, {}).get(course, 3.5)  # default GPA is 3.5

        # Apply gender-specific factor as in the original code
        if sex == "male":
            beta = 0.1185
        elif sex == "female":
            beta = 0.1377
        else:
            # Use average factor if gender not specified
            beta = (0.1185 + 0.1377) / 2

        # Calculate the adjusted salary based on GPA difference
        return raw_salary * np.exp(beta * (GPA - gpa_avg))

    def get_annual_growth(university, course):
        # Default growth rates by university and course
        growth_data = {
            "University A": {
                "Engineering": 0.10,
                "Arts": 0.05,
            },
            "University B": {
                "Engineering": 0.12,
                "Arts": 0.07,
            },
        }
        # Get the annual growth rate, or use default of 10%
        return growth_data.get(university, {}).get(course, 0.1)

    # Calculate initial salary and annual growth rate
    initial_salary = base_salary(GPA, sex, university, course)
    annual_growth = get_annual_growth(university, course)

    # Generate the 10-year salary vector with annual increases
    salary_vector = []
    current_salary = initial_salary

    for month in range(120):
        salary_vector.append(current_salary)
        if (month + 1) % 12 == 0:  # increase salary once per year
            current_salary *= (1 + annual_growth)

    return salary_vector

def test1(emprestimo, juros, GPA, sex, university, course):
    """
    Verifies if the loan can be paid within constraints.
    
    Parameters:
    emprestimo (float): The loan amount in USD.
    juros (float): The monthly interest rate.
    GPA (float): The student's GPA.
    sex (str): The student's sex ('male' or 'female').
    university (str): The university name.
    course (str): The course name.
    
    Returns:
    dict: Results of the loan payment analysis.
    """
    # Get the predicted salary vector
    salary_vec = salary_vector_prediction(GPA, sex, university, course)
    
    # Function to update the debt each month
    def update_divida(months, divida, salary_vec, juros):
        monthly_payment = salary_vec[months] * 0.15  # 15% of income
        payment = min(monthly_payment, divida)
        divida = (divida - payment) * (1 + juros)
        return max(divida, 0)
    
    # Track debt and payment over time
    divida_list = []
    total_pago_list = []
    divida = emprestimo
    total_pago = 0
    months = 0
    
    # Simulate payments until loan is paid, 10 years passed, or total payment exceeds 1.8x loan
    while divida > 0 and months < 120 and total_pago < emprestimo * 1.8:
        divida = update_divida(months, divida, salary_vec, juros)
        divida_list.append(divida)
        total_pago_list.append(total_pago)
        total_pago += salary_vec[months] * 0.15
        months += 1
    
    # Calculate results
    was_paid = divida_list[-1] == 0
    months_left = max(120 - months, 0)
    
    return {
        "was_paid": was_paid,
        "months_left": months_left,
        "expected_return": total_pago,
        "remaining_debt": divida_list[-1]
    }

def test2(threshold, emprestimo, expected_return, country="BR"):
    """
    Checks if the expected value is above the profit threshold.
    
    Parameters:
    threshold (float): The minimum profit ratio.
    emprestimo (float): The loan amount.
    expected_return (float): The expected total payment.
    country (str): The country code for risk assessment.
    
    Returns:
    dict: Results of the risk/profit analysis.
    """
    # Calculate country-specific risk
    def risk(country):
        base_risk = {
            "BR": 0.35,
            "US": 0.12,
            "MX": 0.20,
        }
        return base_risk.get(country.upper(), 0.25)  # default 25%
    
    # Calculate risk and expected value
    risco = risk(country)
    expected_value = (1 - risco) * expected_return + risco * (-emprestimo)
    
    return {
        "worthy": expected_value > threshold * emprestimo,
        "expected_value_profit": expected_value - emprestimo,
        "profit_percentage": (expected_value - emprestimo) / emprestimo,
        "risk": risco
    }

def calculate_loan_options(GPA, university, course, semester_cost=0, remaining_semesters=0, 
                           expected_salary=0, profit_threshold=1.1, country="BR", sex="neutral"):
    """
    Calculate loan options based on student parameters.
    
    Parameters:
    GPA (float): The student's GPA.
    university (str): The university name.
    course (str): The course name.
    semester_cost (float): The cost per semester in USD.
    remaining_semesters (int): The number of semesters left.
    expected_salary (float): The expected salary after graduation.
    profit_threshold (float): The minimum profit ratio.
    country (str): The country code.
    sex (str): The student's sex ('male', 'female', or 'neutral').
    
    Returns:
    list: Viable loan options.
    """
    # If sex is not specified or "neutral", use an average effect
    if sex == "neutral":
        # Try both male and female calculations and average results
        sex = "male"  # Default to male for simplicity
    
    # Calculate total loan amount based on semester cost and semesters left
    loan_amount = 0
    if semester_cost > 0 and remaining_semesters > 0:
        loan_amount = semester_cost * remaining_semesters
    
    # If no specific loan amount, estimate based on expected salary
    if loan_amount == 0 and expected_salary > 0:
        # Use salary to estimate reasonable loan amount
        loan_amount = expected_salary * 2  # Example: 2 years of expected salary
    
    # If we still don't have a loan amount, use defaults
    if loan_amount == 0:
        loan_amount = 50000  # Default loan amount
    
    # Define interest rate options to test
    base_gpa_factor = (4.0 - GPA) / 4.0  # Better GPA = lower rate
    base_rate = 0.09  # 9% annual rate
    
    # Generate different loan options with varied terms
    options = []
    
    # Test different interest rates and terms
    terms_years = [5, 7, 10]  # Loan terms in years
    rate_adjustments = [0.0, -0.005, -0.01]  # Rate adjustments for different terms
    
    for i, term_years in enumerate(terms_years):
        # Calculate monthly terms and adjusted interest rate
        term_months = term_years * 12
        annual_rate = max(0.04, base_rate + (base_gpa_factor * 0.04) + rate_adjustments[i])
        monthly_rate = annual_rate / 12  # Convert to monthly rate
        
        # Test if loan is viable with these parameters
        result1 = test1(
            emprestimo=loan_amount,
            juros=monthly_rate,
            GPA=GPA,
            sex=sex,
            university=university,
            course=course
        )
        
        result2 = test2(
            threshold=profit_threshold,
            emprestimo=loan_amount,
            expected_return=result1["expected_return"],
            country=country
        )
        
        # Calculate monthly payment and total payment
        # PMT = P * r * (1+r)^n / ((1+r)^n - 1)
        monthly_payment = (loan_amount * monthly_rate * (1 + monthly_rate)**term_months) / ((1 + monthly_rate)**term_months - 1)
        total_payment = monthly_payment * term_months
        
        # Create loan option with consistent naming matching PHP expectations
        option = {
            "loan_amount": loan_amount,
            "interest_rate": annual_rate,  # Annual rate in decimal form
            "term_years": term_years,
            "monthly_payment": monthly_payment,
            "total_payment": total_payment,
            "is_viable": result1["was_paid"] and result2["worthy"]
        }
        
        options.append(option)
    
    # Filter to only viable options
    viable_options = [opt for opt in options if opt["is_viable"]]
    
    # If no viable options, return all options but mark them
    if not viable_options:
        for opt in options:
            opt["viability_warning"] = "Este empréstimo pode ser difícil de pagar com as condições atuais."
        return options
    
    return viable_options

def getRange2Offer(gpa, university, course, semester_cost=0, remaining_semesters=8, profit_threshold=1.1, 
                   salario_esperado=0, country='BR'):
    """
    Calcula las opciones de préstamo disponibles para un estudiante
    basado en su perfil académico y otros datos, usando la lógica original.
    
    Args:
        gpa (float): Promedio académico del estudiante (escala 0-4.0)
        university (str): Nombre de la universidad
        course (str): Nombre del curso/carrera
        semester_cost (float): Costo del semestre en USD
        remaining_semesters (int): Número de semestres restantes
        profit_threshold (float): Umbral de ganancia mínima (por defecto 1.1)
        salario_esperado (float): Salario esperado al graduarse en USD
        country (str): País del estudiante
        
    Returns:
        list: Lista de opciones de préstamo disponibles formateadas para la UI
    """
    # Validaciones básicas
    if not university or not course:
        return [{"error": "Universidad o curso no especificados"}]
    
    if not isinstance(gpa, (int, float)) or gpa < 0 or gpa > 4.0:
        return [{"error": "GPA debe ser un número entre 0 y 4.0"}]
    
    # Get loan options using the original algorithm logic
    options = calculate_loan_options(
        GPA=gpa,
        university=university,
        course=course,
        semester_cost=semester_cost,
        remaining_semesters=remaining_semesters,
        expected_salary=salario_esperado,
        profit_threshold=profit_threshold,
        country=country,
        sex="neutral"  # Using neutral by default for new calculations
    )
    
    # Format options to ensure they match the PHP expectations
    formatted_options = []
    for opt in options:
        formatted_option = {
            "loan_amount": round(opt["loan_amount"], 2),
            "interest_rate": round(opt["interest_rate"], 4),
            "term_years": opt["term_years"],
            "monthly_payment": round(opt["monthly_payment"], 2),
            "total_payment": round(opt["total_payment"], 2)
        }
        formatted_options.append(formatted_option)
    
    return formatted_options

# Maintain alias for backward compatibility
get_range_2_offer = getRange2Offer

def main():
    """
    Punto de entrada principal que lee los datos de entrada
    en formato JSON y devuelve los resultados.
    """
    try:
        # Leer datos de entrada JSON de stdin
        input_data = json.loads(sys.stdin.read())
        
        # Extraer parámetros
        gpa = float(input_data.get('gpa', 3.0))
        university = input_data.get('university', '')
        course = input_data.get('course', '')
        semester_cost = float(input_data.get('semester_cost', 0))
        remaining_semesters = int(input_data.get('remaining_semesters', 8))
        profit_threshold = float(input_data.get('profit_threshold', 1.1))
        salario_esperado = float(input_data.get('expected_salary', 0))
        if salario_esperado == 0:
            salario_esperado = float(input_data.get('salario_esperado', 0))
        country = input_data.get('country', 'BR')
        
        # Calcular opciones de préstamo
        loan_options = getRange2Offer(
            gpa, university, course, semester_cost, remaining_semesters,
            profit_threshold, salario_esperado, country
        )
        
        # Formateando el resultado para la interfaz web
        result = {
            "options": loan_options
        }
        
        # Devolver resultado como JSON
        print(json.dumps(result))
        
    except Exception as e:
        # Manejar errores
        error_result = {"error": f"Error: {str(e)}"}
        print(json.dumps(error_result))
        sys.exit(1)

if __name__ == "__main__":
    main()